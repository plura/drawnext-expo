<?php
// backend/lib/Storage.php

namespace Lib;

use RuntimeException;
use Lib\Config;
use Lib\FileHandlerException;

class Storage
{
    /**
     * Gets the configured upload directory with safety checks
     */
    public static function getUploadDir(): string
    {
        // 1. Get configured path
        $configuredDir = trim(Config::get('uploads.directory'), '/.');
        $configuredDir = $configuredDir ?: 'uploads'; // Default

        // 2. Validate path format
        if (preg_match('/(^\/|\.\.)/', $configuredDir)) {
            throw new FileHandlerException(
                "Path must be relative to project root (e.g. 'uploads')",
                ['invalid' => Config::get('uploads.directory')]
            );
        }

        // 3. Resolve absolute path
        $projectRoot = self::getProjectRoot();
        $uploadDir = $projectRoot . '/' . $configuredDir;

        // 4. Verify containment
        self::validatePathContainment($projectRoot, $uploadDir);

        return $uploadDir;
    }

    /**
     * Ensures upload directory exists and is writable
     */
    public static function ensureUploadDir(): string
    {
        $uploadDir = self::getUploadDir();
        return self::ensureWritableDir($uploadDir, 'Upload');
    }

    /**
     * Returns the temp uploads directory (…/uploads/_tmp), creating it if needed.
     */
    public static function tempDir(): string
    {
        $base = self::ensureUploadDir();      // e.g., <PROJECT_ROOT>/uploads
        $dir  = $base . '/_tmp';
        return self::ensureWritableDir($dir, 'Temp upload');
    }

    /**
     * Ensure a directory exists and is writable (create if missing).
     */
    private static function ensureWritableDir(string $dir, string $label): string
    {
        if (!is_dir($dir)) {
            if (!mkdir($dir, 0755, true)) {
                throw new FileHandlerException(
                    "{$label} directory creation failed",
                    ['path' => $dir, 'error' => error_get_last()]
                );
            }
        } elseif (!is_writable($dir)) {
            throw new FileHandlerException(
                "{$label} directory not writable",
                ['path' => $dir]
            );
        }
        return $dir;
    }

    /**
     * Return project root, from constant or fallback.
     */
    private static function getProjectRoot(): string
    {
        if (defined('PROJECT_ROOT')) return PROJECT_ROOT;
        $root = realpath(__DIR__ . '/../../') ?: null;
        if (!$root) throw new RuntimeException("Project root not found");
        return $root;
    }

    /**
     * Prevent escaping project boundaries.
     */
    private static function validatePathContainment(string $root, string $path): void
    {
        $resolvedPath = realpath($path) ?: $path;
        $canonicalRoot = realpath($root) . '/';

        if (strpos($resolvedPath . '/', $canonicalRoot) !== 0) {
            throw new FileHandlerException(
                "Path escapes project boundary",
                ['root' => $root, 'attempted' => $path]
            );
        }
    }
}
