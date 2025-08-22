<?php
// backend/scripts/cleanup_test_data.php

/**
 * cleanup_test_data.php
 *
 * Removes test-only database rows and their associated files.
 *
 * USAGE EXAMPLES:
 *
 *   # Preview deletions without removing anything (safe dry-run)
 *   php backend/scripts/cleanup_test_data.php --dry-run -v
 *
 *   # Actually delete test data (verbose mode)
 *   php backend/scripts/cleanup_test_data.php -v
 *
 *   # Delete test data AND stale temp uploads older than 24h
 *   php backend/scripts/cleanup_test_data.php -v --tmp
 *
 * REQUIREMENTS:
 * - Must run from CLI
 * - Env variable TEST_MODE=1
 * - Env must contain "test"
 */

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

if (!str_contains(strtolower((string)Env::get('ENV', 'production')), 'test')) {
    die("ERROR: Environment must contain 'test'\n");
}

// 3. Argument Parsing
$isDryRun   = in_array('--dry-run', $GLOBALS['argv'], true);
$isVerbose  = in_array('-v', $GLOBALS['argv'], true);
$alsoTmp    = in_array('--tmp', $GLOBALS['argv'], true);   // optional: clean /uploads/_tmp
$tmpMaxAgeH = 24; // only used with --tmp

try {
    $db        = dependencies()['db'];
    $uploadDir = rtrim(Storage::getUploadDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    $tmpDir    = $uploadDir . '_tmp' . DIRECTORY_SEPARATOR;

    // 4. Pre-Cleanup Stats
    $stats = [
        'files'     => (int)($db->querySingle("SELECT COUNT(*) AS c FROM files WHERE test = 1")['c'] ?? 0),
        'drawings'  => (int)($db->querySingle("SELECT COUNT(*) AS c FROM drawings WHERE test = 1")['c'] ?? 0),
        'neighbors' => (int)($db->querySingle("
            SELECT COUNT(*) AS c
            FROM drawing_neighbors 
            WHERE drawing_id IN (SELECT drawing_id FROM drawings WHERE test = 1)
        ")['c'] ?? 0),
        'users'     => (int)($db->querySingle("SELECT COUNT(*) AS c FROM users WHERE test = 1")['c'] ?? 0),
    ];

    if ($isVerbose) {
        echo "Test data found:\n"
           . "- Files rows: {$stats['files']}\n"
           . "- Drawings: {$stats['drawings']}\n"
           . "- Neighbor Relationships: {$stats['neighbors']}\n"
           . "- Users: {$stats['users']}\n\n";
    }

    // 5. Collect filenames from DB (test=1)
    $rows = $db->query("SELECT stored_filename FROM files WHERE test = 1");
    $dbFiles = array_map(static fn($r) => (string)$r['stored_filename'], $rows);

    // 6. Build filesystem deletion set:
    //    - Always include the stored filename
    //    - If it ends with __display.webp, also include the sibling __thumb.webp (derived, not in DB)
    $pathsToDelete = [];
    foreach ($dbFiles as $sf) {
        $displayPath = $uploadDir . $sf;
        $pathsToDelete[$displayPath] = true;

        if (preg_match('/__display\.webp$/i', $sf)) {
            $thumb = preg_replace('/__display\.webp$/i', '__thumb.webp', $sf);
            $thumbPath = $uploadDir . $thumb;
            $pathsToDelete[$thumbPath] = true;
        }
    }

    if ($isVerbose) {
        echo "Planned file deletions (including derived thumbs):\n";
        foreach (array_keys($pathsToDelete) as $p) {
            echo ($isDryRun ? "[DRY RUN] " : "") . $p . "\n";
        }
        echo "\n";
    }

    // 7. Database Cleanup (in order)
    if (!$isDryRun) {
        $db->beginTransaction();
        try {
            $db->execute("
                DELETE FROM drawing_neighbors 
                WHERE drawing_id IN (SELECT drawing_id FROM drawings WHERE test = 1)
            ");
            $db->execute("DELETE FROM files WHERE test = 1");
            $db->execute("DELETE FROM drawings WHERE test = 1");
            $db->execute("DELETE FROM users WHERE test = 1");
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }
    }

    // 8. Filesystem Cleanup
    $deletedCount = 0;
    $totalPlanned = count($pathsToDelete);

    foreach (array_keys($pathsToDelete) as $path) {
        if ($isVerbose) {
            echo ($isDryRun ? "[DRY RUN] " : "") . "Delete: $path\n";
        }
        if ($isDryRun) {
            $deletedCount++;
            continue;
        }
        if (is_file($path)) {
            if (@unlink($path)) {
                $deletedCount++;
            } else {
                error_log("Failed to delete: $path");
            }
        }
    }

    // 9. Optional: clean /uploads/_tmp (stale tokens)
    $tmpDeleted = 0;
    $tmpPlanned = 0;
    if ($alsoTmp && is_dir($tmpDir)) {
        $now = time();
        $dh = opendir($tmpDir);
        if ($dh) {
            while (($entry = readdir($dh)) !== false) {
                if ($entry === '.' || $entry === '..') continue;
                $full = $tmpDir . $entry;
                if (!is_file($full)) continue;

                $ageHours = ($now - filemtime($full)) / 3600;
                if ($ageHours >= $tmpMaxAgeH) {
                    $tmpPlanned++;
                    if ($isVerbose) {
                        echo ($isDryRun ? "[DRY RUN] " : "") . "TMP delete: $full\n";
                    }
                    if ($isDryRun) {
                        $tmpDeleted++;
                    } else {
                        if (@unlink($full)) $tmpDeleted++;
                    }
                }
            }
            closedir($dh);
        }
    }

    // 10. Results
    echo sprintf(
        "\nCleanup %s:\n"
        . "- Files rows (test=1): %d\n"
        . "- Drawings rows (test=1): %d\n"
        . "- Neighbor rows (test=1 drawings): %d\n"
        . "- Users rows (test=1): %d\n"
        . "- File deletions: %s%d/%d\n"
        . "%s",
        $isDryRun ? "simulation complete" : "complete",
        $stats['files'],
        $stats['drawings'],
        $stats['neighbors'],
        $stats['users'],
        $isDryRun ? "[DRY RUN] " : "",
        $deletedCount,
        $totalPlanned,
        $alsoTmp
            ? sprintf("- Temp deletions (>%dh): %s%d/%d\n", $tmpMaxAgeH, $isDryRun ? "[DRY RUN] " : "", $tmpDeleted, $tmpPlanned)
            : ""
    );

} catch (\Throwable $e) {
    error_log("[" . date('c') . "] CLEANUP ERROR: " . $e->getMessage());
    die("FAILED: " . $e->getMessage() . "\n");
}
