<?php
// backend/lib/Storage.php

namespace Lib;

use RuntimeException;
use Lib\Config;
use Lib\FileHandlerException;

class Storage {
    /**
     * Gets the configured upload directory with safety checks
     */
    public static function getUploadDir(): string {
        // 1. Get configured path
        $configuredDir = trim(Config::get('uploads.directory'), '/.');
        $configuredDir = $configuredDir ?: 'uploads'; // Default
        
        // 2. Validate path format
        if (preg_match('/(^\/|\.\.)/', $configuredDir)) {
            throw new FileHandlerException(
                "Path must be relative to project root (e.g. 'uploads')",
                ['invalid' => Config::get('upload_directory')]
            );
        }
        
        // 3. Resolve absolute path
        $projectRoot = self::getProjectRoot();
        $uploadDir = $projectRoot.'/'.$configuredDir;
        
        // 4. Verify containment
        self::validatePathContainment($projectRoot, $uploadDir);
        
        return $uploadDir;
    }

    /**
     * Ensures upload directory exists and is writable
     */
    public static function ensureUploadDir(): string {
        $uploadDir = self::getUploadDir();
        
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new FileHandlerException(
                    "Directory creation failed",
                    ['path' => $uploadDir, 'error' => error_get_last()]
                );
            }
        } elseif (!is_writable($uploadDir)) {
            throw new FileHandlerException(
                "Upload directory not writable",
                ['path' => $uploadDir]
            );
        }
        
        return $uploadDir;
    }

    private static function getProjectRoot(): string {
        $root = realpath(__DIR__.'/../../');
        if (!$root) {
            throw new RuntimeException("Project root not found");
        }
        return $root;
    }

    private static function validatePathContainment(string $root, string $path): void {
        $resolvedPath = realpath($path) ?: $path;
        $canonicalRoot = realpath($root).'/';
        
        if (strpos($resolvedPath.'/', $canonicalRoot) !== 0) {
            throw new FileHandlerException(
                "Path escapes project boundary",
                ['root' => $root, 'attempted' => $path]
            );
        }
    }
}