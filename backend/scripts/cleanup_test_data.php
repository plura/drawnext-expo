<?php
// backend/scripts/cleanup_test_data.php

declare(strict_types=1);

// 1. CLI Execution Check
if (PHP_SAPI !== 'cli') {
    die("ERROR: Script must be run from command line\n");
}

require __DIR__ . '/../bootstrap.php';

use Lib\{Env, Storage};

// 2. Environment Safety
if ((int)Env::get('TEST_MODE', 0) !== 1) {
    die("ERROR: TEST_MODE=1 required\n");
}

if (!str_contains(strtolower(Env::get('ENV', 'production')), 'test')) {
    die("ERROR: Environment must contain 'test'\n");
}

// 3. Argument Parsing
$isDryRun = in_array('--dry-run', $GLOBALS['argv']);
$isVerbose = in_array('-v', $GLOBALS['argv']);

try {
    $db = dependencies()['db'];
    $uploadDir = Storage::getUploadDir() . DIRECTORY_SEPARATOR;
    
    // 4. Pre-Cleanup Stats
    $stats = [
        'files' => $db->querySingle("SELECT COUNT(*) FROM files WHERE test = 1")['COUNT(*)'],
        'drawings' => $db->querySingle("SELECT COUNT(*) FROM drawings WHERE test = 1")['COUNT(*)'],
        'users' => $db->querySingle("SELECT COUNT(*) FROM users WHERE test = 1")['COUNT(*)']
    ];

    if ($isVerbose) {
        echo "Test data found:\n"
           . "- Files: {$stats['files']}\n"
           . "- Drawings: {$stats['drawings']}\n"
           . "- Users: {$stats['users']}\n\n";
    }

    // 5. File Discovery
    $files = $db->query(
        "SELECT stored_filename FROM files 
         WHERE test = 1 
         AND stored_filename REGEXP '^[a-f0-9]{32}\\\.[a-z0-9]{3,4}$'"
    );

    // 6. Database Cleanup
    if (!$isDryRun) {
        $db->beginTransaction();
        $db->execute("DELETE FROM files WHERE test = 1");
        $db->execute("DELETE FROM drawings WHERE test = 1");
        $db->execute("DELETE FROM users WHERE test = 1");
        $db->commit();
    }

    // 7. Filesystem Cleanup
    $deletedCount = 0;
    foreach ($files as $file) {
        $path = $uploadDir . $file['stored_filename'];
        
        if ($isVerbose) {
            echo ($isDryRun ? "[DRY RUN] " : "") . "Processing: {$file['stored_filename']}\n";
        }

        if (!$isDryRun && file_exists($path)) {
            unlink($path) ? $deletedCount++ : error_log("Failed to delete: $path");
        } elseif ($isDryRun) {
            $deletedCount++; // Count for report
        }
    }

    // 8. Results
    echo sprintf(
        "\nCleanup %s:\n"
        . "- %sFiles: %d\n"
        . "- %sDrawings: %d\n"
        . "- %sUsers: %d\n"
        . "- %sFiles Deleted: %d/%d\n",
        $isDryRun ? "simulation complete" : "complete",
        $isDryRun ? "[DRY RUN] " : "", $stats['files'],
        $isDryRun ? "[DRY RUN] " : "", $stats['drawings'],
        $isDryRun ? "[DRY RUN] " : "", $stats['users'],
        $isDryRun ? "[DRY RUN] " : "", $deletedCount, count($files)
    );

} catch (Throwable $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("[" . date('c') . "] CLEANUP ERROR: " . $e->getMessage());
    die("FAILED: " . $e->getMessage() . "\n");
}