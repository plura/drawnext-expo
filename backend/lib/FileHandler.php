<?php
// backend/lib/FileHandler.php
namespace Lib;

use Lib\FileHandlerException;
use RuntimeException;

class FileHandler
{
	/**
	 * Process and persist an uploaded file directly into the permanent uploads area.
	 *
	 * @param array $uploadedFile  The $_FILES-style array for the uploaded file.
	 * @param bool  $isTest        If true, use copy() instead of move_uploaded_file().
	 * 
	 * @return array{
	 *   stored_filename: string,
	 *   original_filename: string,
	 *   filesize: int,
	 *   mime_type: string,
	 *   width: int,
	 *   height: int,
	 *   filepath: string,
	 *   is_test_copy: bool
	 * }
	 *
	 * @throws FileHandlerException|RuntimeException
	 */
	public static function processUpload(array $uploadedFile, bool $isTest = false): array
	{
		$targetPath = null;

		try {
			self::validateUpload($uploadedFile);
			$targetPath = self::generateTargetPath($uploadedFile['name']);

			// Move/copy the temp file
			if ($isTest) {
				if (!copy($uploadedFile['tmp_name'], $targetPath)) {
					throw new FileHandlerException("Test file copy failed", error_get_last() ?: []);
				}
			} else {
				if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
					throw new FileHandlerException("File upload failed", error_get_last() ?: []);
				}
			}

			$mime = self::getMimeType($targetPath);

			// Check allowed MIME types
			$allowed = Config::get('uploads.allowed_mime_types');
			if (!empty($allowed) && !in_array($mime, (array)$allowed, true)) {
				throw new FileHandlerException("File type not allowed", [
					'mime' => $mime,
					'allowed' => $allowed,
				]);
			}

			[$width, $height] = self::getImageDimensions($targetPath);

			return [
				'stored_filename'   => basename($targetPath),
				'original_filename' => $uploadedFile['name'],
				'filesize'          => (int)$uploadedFile['size'],
				'mime_type'         => $mime,
				'width'             => $width,
				'height'            => $height,
				'filepath'          => $targetPath,
				'is_test_copy'      => $isTest,
			];
		} catch (FileHandlerException $e) {
			if ($targetPath && file_exists($targetPath)) {
				@unlink($targetPath);
			}
			throw $e;
		}
	}

	/**
	 * Save an uploaded file into the temp area, returning a token and metadata.
	 *
	 * @param array $uploadedFile  The $_FILES-style array for the uploaded file.
	 * @param bool  $isTest        If true, copy() instead of move_uploaded_file().
	 * 
	 * @return array{
	 *   token: string,
	 *   width: int,
	 *   height: int,
	 *   hash: string
	 * }
	 *
	 * @throws FileHandlerException|RuntimeException
	 */
	public static function saveTempUpload(array $uploadedFile, bool $isTest = false): array
	{
		self::validateUpload($uploadedFile);

		$ext   = strtolower(pathinfo($uploadedFile['name'] ?? '', PATHINFO_EXTENSION));
		$token = bin2hex(random_bytes(16));

		$tempDir  = Storage::tempDir();
		$tempPath = $tempDir . '/' . $token . ($ext ? ('.' . $ext) : '');

		if ($isTest) {
			if (!copy($uploadedFile['tmp_name'], $tempPath)) {
				throw new FileHandlerException("Temp file copy failed", error_get_last() ?: []);
			}
		} else {
			if (!move_uploaded_file($uploadedFile['tmp_name'], $tempPath)) {
				throw new FileHandlerException("Temp file move failed", error_get_last() ?: []);
			}
		}

		$mime    = self::getMimeType($tempPath);
		$allowed = Config::get('uploads.allowed_mime_types');
		if (!empty($allowed) && !in_array($mime, (array)$allowed, true)) {
			@unlink($tempPath);
			throw new FileHandlerException("File type not allowed", ['mime' => $mime, 'allowed' => $allowed]);
		}

		[$width, $height] = self::getImageDimensions($tempPath);
		$hash = sha1_file($tempPath) ?: '';

		$sidecar = ['original_name' => (string)($uploadedFile['name'] ?? '')];
		@file_put_contents($tempDir . '/' . $token . '.json', json_encode($sidecar));

		return [
			'token'  => $token,
			'width'  => $width,
			'height' => $height,
			'hash'   => $hash,
		];
	}

	/**
	 * Finalize a previously temp-uploaded file by token:
	 * - Moves it from /uploads/_tmp into the permanent uploads dir
	 * - Optimizes into WebP display + thumb (keeps only display for DB)
	 * - Falls back to original if optimization fails
	 *
	 * @param string $token  The opaque upload token returned by saveTempUpload()
	 * @return array {
	 *   stored_filename: string,
	 *   original_filename: string,
	 *   filesize: int,
	 *   mime_type: string,
	 *   width: int,
	 *   height: int,
	 *   filepath: string,
	 *   is_test_copy: bool
	 * }
	 * @throws \Lib\FileHandlerException on invalid token or file issues
	 */
	public static function finalizeFromToken(string $token): array
	{
		// 1) Sanitize token
		$token = preg_replace('/[^a-f0-9]/i', '', $token);
		if (!$token) {
			throw new FileHandlerException("Invalid upload token");
		}

		// 2) Locate temp file + sidecar
		[$tempPath, $sidecarPath] = self::findTempFiles($token);
		if (!$tempPath) {
			throw new FileHandlerException("Upload token not found or expired");
		}

		// 3) Validate real MIME against allowed list (defense in depth)
		$mime    = self::getMimeType($tempPath);
		$allowed = Config::get('uploads.allowed_mime_types');
		if (!empty($allowed) && !in_array($mime, (array)$allowed, true)) {
			@unlink($tempPath);
			@unlink($sidecarPath);
			throw new FileHandlerException("File type not allowed", ['mime' => $mime, 'allowed' => $allowed]);
		}

		// 4) Restore original filename (if present in sidecar), else use temp basename
		$originalName = basename($tempPath);
		if (is_file($sidecarPath)) {
			$meta = json_decode((string)file_get_contents($sidecarPath), true);
			if (is_array($meta) && !empty($meta['original_name'])) {
				$originalName = (string)$meta['original_name'];
			}
		}

		// 5) Build final destination using random name strategy
		$targetPath = self::generateTargetPath($originalName);

		// 6) Move temp → permanent and remove sidecar
		if (!@rename($tempPath, $targetPath)) {
			throw new FileHandlerException("Failed to move file into uploads", ['from' => $tempPath, 'to' => $targetPath]);
		}
		@unlink($sidecarPath);

		// 7) Optimize (convert to WebP display + thumb). If it fails, keep the moved original.
		try {
			$optMeta = self::optimizeImage($targetPath); // may throw

			$finalBasename = $optMeta['stored_filename'];
			$finalPath     = $optMeta['filepath'];
			$filesize      = $optMeta['filesize'];
			$width         = $optMeta['width'];
			$height        = $optMeta['height'];
			$mimeType      = $optMeta['mime_type'];
		} catch (\Throwable $optE) {
			// Optimization failed — keep the original file and log the error
			error_log("Image optimization failed: " . $optE->getMessage());

			$finalPath     = $targetPath;
			$finalBasename = basename($targetPath);
			$filesize      = (int) (filesize($finalPath) ?: 0);
			[$width, $height] = self::getImageDimensions($finalPath);
			$mimeType       = self::getMimeType($finalPath);
		}

		// 8) Return meta (same shape as processUpload())
		return [
			'stored_filename'   => $finalBasename,
			'original_filename' => $originalName,
			'filesize'          => $filesize,
			'mime_type'         => $mimeType,
			'width'             => $width,
			'height'            => $height,
			'filepath'          => $finalPath,
			'is_test_copy'      => false,
		];
	}


	/**
	 * Delete a file from disk (unless test copy).
	 *
	 * @param array $fileMeta  Metadata array returned by FileHandler.
	 */
	public static function cleanup(array $fileMeta): void
	{
		if (!($fileMeta['is_test_copy'] ?? false) && !empty($fileMeta['filepath'])) {
			@unlink($fileMeta['filepath']);
		}
	}

	// ========== PRIVATE HELPERS ========== //

	/** Generate a random permanent filename in the uploads dir. */
	private static function generateTargetPath(string $originalName): string
	{
		$uploadDir = Storage::ensureUploadDir();
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		$random = bin2hex(random_bytes(16));

		// TODO (future): consider hashing into subdirectories for scalability,
		// e.g. $uploadDir . '/' . substr($random, 0, 2) . '/' . $random ...
		// This keeps directories small if uploads grow very large.
		return $uploadDir . '/' . $random . ($extension ? ".$extension" : '');
	}


	/** Validate upload errors and file size. */
	private static function validateUpload(array $file): void
	{
		if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			throw new RuntimeException(self::getUploadError($file['error'] ?? -1));
		}

		$maxSizeMb    = (int) Config::get('uploads.max_size_mb');
		$maxSizeBytes = $maxSizeMb * 1024 * 1024;

		if (isset($file['size']) && $file['size'] > $maxSizeBytes) {
			throw new RuntimeException(sprintf("File exceeds %dMB limit", $maxSizeMb));
		}
	}

	/** Get image width/height. */
	private static function getImageDimensions(string $path): array
	{
		$dimensions = @getimagesize($path);
		if ($dimensions === false) {
			throw new FileHandlerException("Invalid image file");
		}
		return [$dimensions[0] ?? null, $dimensions[1] ?? null];
	}

	/** Get MIME type using finfo. */
	private static function getMimeType(string $path): string
	{
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($path);
		if (!$mimeType) {
			throw new FileHandlerException("Could not determine file type");
		}
		return $mimeType;
	}

	/** Human-readable error string for upload error codes. */
	private static function getUploadError(int $code): string
	{
		return [
			UPLOAD_ERR_INI_SIZE   => "File exceeds server size limit",
			UPLOAD_ERR_FORM_SIZE  => "File exceeds form size limit",
			UPLOAD_ERR_PARTIAL    => "File only partially uploaded",
			UPLOAD_ERR_NO_FILE    => "No file uploaded",
			UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
			UPLOAD_ERR_CANT_WRITE => "Failed to write file",
			UPLOAD_ERR_EXTENSION  => "File type not allowed",
		][$code] ?? "Unknown upload error (Code: $code)";
	}

	/**
	 * Find temp file & sidecar metadata by token.
	 *
	 * @param string $token
	 * @return array{0:?string,1:string} [tempPathOrNull, sidecarPath]
	 */
	private static function findTempFiles(string $token): array
	{
		$dir = Storage::tempDir();
		$sidecarPath = $dir . '/' . $token . '.json';

		$matches = glob($dir . '/' . $token . '.*', GLOB_NOSORT) ?: [];
		$matches = array_values(array_filter($matches, fn($p) => !str_ends_with($p, '.json')));

		$tempPath = $matches[0] ?? null;
		return [$tempPath, $sidecarPath];
	}


	/**
	 * Optimize a just-moved original into WebP derivatives and return meta for the display file.
	 * Throws on failure; caller decides fallback policy.
	 *
	 * @param string $targetPath Absolute path to the moved original (with extension)
	 * @return array { stored_filename, filesize, mime_type, width, height, filepath, is_test_copy:false }
	 * @throws \Throwable if optimization fails
	 */
	private static function optimizeImage(string $targetPath): array
	{
		// Use the final path (without extension) as stem for derivative outputs
		$stem = preg_replace('/\.[a-z0-9]+$/i', '', $targetPath);

		// Produce {display, thumb} webp files
		$derivatives = Image::optimizeWebp($targetPath, $stem);
		$display     = $derivatives['display'] ?? null;

		// Remove the moved original to save space (we serve the display variant)
		@unlink($targetPath);

		if (!$display || empty($display['path'])) {
			throw new \RuntimeException("Optimization did not return a display derivative");
		}

		$finalPath     = $display['path'];
		$finalBasename = basename($finalPath);
		$filesize      = (int) ($display['bytes'] ?? (filesize($finalPath) ?: 0));

		// Width/height were captured during encoding; fallback to probing on disk if missing
		$width  = (int) ($display['width'] ?? 0);
		$height = (int) ($display['height'] ?? 0);
		if ($width <= 0 || $height <= 0) {
			[$width, $height] = self::getImageDimensions($finalPath);
		}

		return [
			'stored_filename' => $finalBasename,
			'filesize'        => $filesize,
			'mime_type'       => 'image/webp',
			'width'           => $width,
			'height'          => $height,
			'filepath'        => $finalPath,
			'is_test_copy'    => false,
		];
	}
}
