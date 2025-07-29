<?php
// backend/lib/FileHandler.php

namespace Lib;

use Lib\FileHandlerException;
use RuntimeException;

class FileHandler 
{
    public static function processUpload(array $uploadedFile, int $drawingId): array 
{
    try {
        $isTest = Env::get('TEST_MODE', false);
        
        // Validate early
        self::validateUpload($uploadedFile);
        
        // Prepare paths and generate secure filename
        $extension = strtolower(pathinfo($uploadedFile['name'], PATHINFO_EXTENSION));
        $secureFilename = bin2hex(random_bytes(16)) . ($extension ? ".$extension" : '');
        $targetPath = self::generateTargetPath($secureFilename);
        
        $context = [
            'source' => $uploadedFile['tmp_name'],
            'target' => $targetPath,
            'is_test' => $isTest
        ];

        // Handle file transfer
        if ($isTest) {
            if (!copy($uploadedFile['tmp_name'], $targetPath)) {
                throw new FileHandlerException(
                    "Failed to copy test file",
                    $context + ['last_error' => error_get_last()]
                );
            }
        } else {
            if (!move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                throw new FileHandlerException(
                    "Failed to move uploaded file", 
                    $context + ['last_error' => error_get_last()]
                );
            }
        }

        // Get image dimensions and MIME type
        $dimensions = getimagesize($targetPath);
        if ($dimensions === false) {
            throw new FileHandlerException("Invalid image file", $context);
        }
        
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->file($targetPath);
        if (!$mimeType) {
            throw new FileHandlerException("Could not determine file type", $context);
        }

        // Return complete metadata
        return [
            'drawing_id' => $drawingId,
            'stored_filename' => $secureFilename,
            'original_filename' => $uploadedFile['name'],
            'filesize' => (int)$uploadedFile['size'],
            'mime_type' => $mimeType,
            'width' => (int)$dimensions[0],
            'height' => (int)$dimensions[1],
            'test' => $isTest,
            'filepath' => $targetPath // Still included for internal use
        ];

    } catch (FileHandlerException $e) {
        // Clean up if file was partially processed
        if (!empty($targetPath) && file_exists($targetPath)) {
            @unlink($targetPath);
        }
        
        throw new FileHandlerException(
            "Upload processing failed: " . $e->getMessage(),
            $e->getContext()
        );
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

    private static function generateTargetPath(string $originalName): string 
    {
        $uploadDir = self::ensureUploadDirectory();
        $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = bin2hex(random_bytes(16)) . ($ext ? ".$ext" : '');
        
        return "$uploadDir/$filename";
    }

    private static function ensureUploadDirectory(): string {
        // 1. Get configured path
        $configuredDir = trim(Config::get('upload_directory'), '/.');
        $configuredDir = $configuredDir ?: 'uploads'; // Default
        
        // 2. Validate path format
        if (preg_match('/(^\/|\.\.)/', $configuredDir)) {
            throw new FileHandlerException(
                "Path must be relative to project root (e.g. 'uploads')",
                ['invalid' => Config::get('upload_directory')]
            );
        }
        
        // 3. Resolve absolute path
        $projectRoot = realpath(__DIR__.'/../../') or throw new RuntimeException("Project root not found");
        $uploadDir = $projectRoot.'/'.$configuredDir;
        
        // 4. Verify containment (canonicalized)
        $resolvedPath = realpath($uploadDir) ?: $uploadDir;
        $canonicalRoot = realpath($projectRoot).'/';
        if (strpos($resolvedPath.'/', $canonicalRoot) !== 0) {
            throw new FileHandlerException(
                "Path escapes project boundary",
                ['root' => $projectRoot, 'attempted' => $uploadDir]
            );
        }
        
        // 5. Create directory
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true)) {
            throw new FileHandlerException(
                "Directory creation failed",
                ['path' => $uploadDir, 'error' => error_get_last()]
            );
        }
        
        return $uploadDir;
    }

    private static function getMimeType(string $path): string 
    {
        return (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
    }

    private static function getImageWidth(string $path): ?int 
    {
        return getimagesize($path)[0] ?? null;
    }

    private static function getImageHeight(string $path): ?int 
    {
        return getimagesize($path)[1] ?? null;
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