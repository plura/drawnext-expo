<?php
// backend/lib/FileServer.php

namespace Lib;

use RuntimeException;
use Lib\Database;

class FileServer {
    public static function serveFile(int $fileId, int $userId): void {
        $db = Database::getInstance();
        $file = $db->querySingle(
            "SELECT * FROM files 
             WHERE file_id = ? AND drawing_id IN (
                 SELECT drawing_id FROM drawings WHERE user_id = ?
             )", 
            [$fileId, $userId]
        );

        if (!$file) {
            throw new RuntimeException("File not found or access denied");
        }

        // Security headers
        header('Content-Type: ' . $file['mime_type']);
        header('Content-Length: ' . $file['filesize']);
        header('Content-Disposition: inline; filename="' . $file['original_filename'] . '"');
        
        // Prevent caching of sensitive files
        header('Cache-Control: private, max-age=120');

        readfile($file['filepath']);
        exit;
    }

    public static function deleteFile(int $fileId, int $userId): bool {
        $db = Database::getInstance();
        $file = $db->querySingle(
            "SELECT filepath FROM files 
             WHERE file_id = ? AND drawing_id IN (
                 SELECT drawing_id FROM drawings WHERE user_id = ?
             )",
            [$fileId, $userId]
        );

        if (!$file) return false;

        // Atomic delete
        $db->beginTransaction();
        try {
            $db->execute("DELETE FROM files WHERE file_id = ?", [$fileId]);
            unlink($file['filepath']);
            $db->commit();
            return true;
        } catch (\Exception $e) {
            $db->rollBack();
            throw new RuntimeException("Failed to delete file: " . $e->getMessage());
        }
    }
}