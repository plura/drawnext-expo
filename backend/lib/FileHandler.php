<?php
// backend/lib/FileHandler.php
namespace Lib;

use Lib\FileHandlerException;
use RuntimeException;

class FileHandler
{
	/**
	 * Processes and validates a file upload
	 */
	public static function processUpload(
		array $uploadedFile,
		bool $isTest = false
	): array {
		$targetPath = null;

		try {
			self::validateUpload($uploadedFile);
			$targetPath = self::generateTargetPath($uploadedFile['name']);

			// Move/copy the temp file
			if ($isTest) {
                // In tests, we allow copy() from tmp to target to avoid move_uploaded_file checks.
				if (!copy($uploadedFile['tmp_name'], $targetPath)) {
					throw new FileHandlerException(
						"Test file copy failed",
						error_get_last() ?: []
					);
				}
			} else {
				if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
					throw new FileHandlerException(
						"File upload failed",
						error_get_last() ?: []
					);
				}
			}

			// Gather metadata (compute once)
			$mime = self::getMimeType($targetPath);

			// Enforce allowed MIME types from config (if set)
			$allowed = Config::get('uploads.allowed_mime_types'); // csv -> array via Config::parse
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
				'filesize'          => (int) $uploadedFile['size'],
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
	 * Cleans up uploaded files
	 */
	public static function cleanup(array $fileMeta): void
	{
		if (!($fileMeta['is_test_copy'] ?? false) && !empty($fileMeta['filepath'])) {
			@unlink($fileMeta['filepath']);
		}
	}

	// ===== PRIVATE METHODS ===== //

	private static function generateTargetPath(string $originalName): string
	{
		$uploadDir = Storage::ensureUploadDir();
		$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
		return $uploadDir . '/' . bin2hex(random_bytes(16)) . ($extension ? ".$extension" : '');
	}

	private static function validateUpload(array $file): void
	{
		if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
			throw new RuntimeException(self::getUploadError($file['error'] ?? -1));
		}

		$maxSizeMb = (int) Config::get('uploads.max_size_mb');
		$maxSizeBytes = $maxSizeMb * 1024 * 1024;

		if (isset($file['size']) && $file['size'] > $maxSizeBytes) {
			throw new RuntimeException(sprintf(
				"File exceeds %dMB limit",
				$maxSizeMb
			));
		}
	}

	private static function getImageDimensions(string $path): array
	{
		$dimensions = @getimagesize($path);
		if ($dimensions === false) {
			throw new FileHandlerException("Invalid image file");
		}
		return [$dimensions[0] ?? null, $dimensions[1] ?? null];
	}

	private static function getMimeType(string $path): string
	{
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mimeType = $finfo->file($path);
		if (!$mimeType) {
			throw new FileHandlerException("Could not determine file type");
		}
		return $mimeType;
	}

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
}
