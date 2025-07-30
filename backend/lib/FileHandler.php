<?php
// backend/lib/FileHandler.php

namespace Lib;

use Lib\FileHandlerException;
use RuntimeException;

class FileHandler 
{
    public static function processUpload(array $uploadedFile, int $drawingId, bool $isTest = false): array 
    {
        $targetPath = null; // Initialize for error handling
        
        try {
            // Validate file structure and content
            self::validateUpload($uploadedFile);
            
            // Generate secure filename and target path
            $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
            $secureFilename = bin2hex(random_bytes(16)) . ($extension ? ".$extension" : '');
            $targetPath = self::generateTargetPath($secureFilename);
            
            // Handle file transfer based on test mode
            if ($isTest) {
                if (!copy($uploadedFile['tmp_name'], $targetPath)) {
                    throw new FileHandlerException(
                        "Failed to copy test file",
                        [
                            'source' => $uploadedFile['tmp_name'],
                            'target' => $targetPath,
                            'error' => error_get_last()
                        ]
                    );
                }
            } else {
                if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                    throw new FileHandlerException(
                        "Failed to move uploaded file", 
                        [
                            'source' => $uploadedFile['tmp_name'],
                            'target' => $targetPath,
                            'error' => error_get_last()
                        ]
                    );
                }
            }

            // Validate image and extract metadata
            $dimensions = getimagesize($targetPath);
            if ($dimensions === false) {
                throw new FileHandlerException("Invalid image file", ['path' => $targetPath]);
            }
            
            $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($targetPath);
            if (!$mimeType) {
                throw new FileHandlerException("Could not determine file type", ['path' => $targetPath]);
            }

            return [
                'drawing_id' => $drawingId,
                'stored_filename' => $secureFilename,
                'original_filename' => $uploadedFile['name'],
                'filesize' => (int)$uploadedFile['size'],
                'mime_type' => $mimeType,
                'width' => (int)$dimensions[0],
                'height' => (int)$dimensions[1],
                'test' => $isTest,
                'filepath' => $targetPath // For internal use only
            ];

        } catch (FileHandlerException $e) {
            // Cleanup if file was partially processed
            if ($targetPath && file_exists($targetPath)) {
                @unlink($targetPath);
            }
            throw $e;
        }
    }

    private static function validateUpload(array $file): void 
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException(self::getUploadError($file['error']));
        }

        $maxSize = (int) Config::get('max_upload_size') * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new RuntimeException(sprintf(
                "File exceeds %dMB limit", 
                $maxSize / 1024 / 1024
            ));
        }
    }

    // Remove ensureUploadDirectory() and replace calls with:
    private static function generateTargetPath(string $originalName): string {
        $uploadDir = Storage::ensureUploadDir();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        return $uploadDir.'/'.bin2hex(random_bytes(16)).($ext ? ".$ext" : '');
    }

    private static function getUploadError(int $code): string 
    {
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

    public static function cleanup(array $file): void 
    {
        if (!($file['is_test_copy'] ?? false) && !empty($file['filepath'])) {
            @unlink($file['filepath']);
        }
    }
}