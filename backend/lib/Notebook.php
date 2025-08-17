<?php

// backend/lib/Notebook.php
namespace Lib;

use Lib\Database;

class Notebook {
    /**
     * Gets the number of sections for a notebook
     */
    public static function getSectionCount(Database $db, int $notebookId): int {
        return (int) $db->querySingle(
            "SELECT COUNT(*) FROM sections WHERE notebook_id = ?",
            [$notebookId]
        )['COUNT(*)'] ?? 0;
    }
}