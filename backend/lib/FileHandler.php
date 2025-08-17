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
            
            // Handle file transfer
            if ($isTest) {
                if (!copy($uploadedFile['tmp_name'], $targetPath)) {
                    throw new FileHandlerException(
                        "Test file copy failed",
                        error_get_last()
                    );
                }
            } else {
                if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                    throw new FileHandlerException(
                        "File upload failed", 
                        error_get_last()
                    );
                }
            }

            return [
                'stored_filename' => basename($targetPath),
                'original_filename' => $uploadedFile['name'],
                'filesize' => (int)$uploadedFile['size'],
                'mime_type' => self::getMimeType($targetPath),
                'width' => self::getImageDimensions($targetPath)[0],
                'height' => self::getImageDimensions($targetPath)[1],
                'filepath' => $targetPath,
                'is_test_copy' => $isTest
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
    public static function cleanup(array $fileMeta): void {
        if (!($fileMeta['is_test_copy'] ?? false) && !empty($fileMeta['filepath'])) {
            @unlink($fileMeta['filepath']);
        }
    }

    // ===== PRIVATE METHODS ===== //

    private static function generateTargetPath(string $originalName): string {
        $uploadDir = Storage::ensureUploadDir();
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return $uploadDir . '/' . bin2hex(random_bytes(16)) . ($extension ? ".$extension" : '');
    }

    private static function validateUpload(array $file): void {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::getUploadError($file['error']));
        }

        $maxSize = (int)Config::get('max_upload_size') * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new RuntimeException(sprintf(
                "File exceeds %dMB limit", 
                $maxSize / 1024 / 1024
            ));
        }
    }

    private static function getImageDimensions(string $path): array {
        $dimensions = getimagesize($path);
        if ($dimensions === false) {
            throw new FileHandlerException("Invalid image file");
        }
        return $dimensions;
    }

    private static function getMimeType(string $path): string {
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!$mimeType) {
            throw new FileHandlerException("Could not determine file type");
        }
        return $mimeType;
    }

    private static function getUploadError(int $code): string {
        return [
            UPLOAD_ERR_INI_SIZE => "File exceeds server size limit",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form size limit",
            UPLOAD_ERR_PARTIAL => "File only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file",
            UPLOAD_ERR_EXTENSION => "File type not allowed"
        ][$code] ?? "Unknown upload error (Code: $code)";
    }
}