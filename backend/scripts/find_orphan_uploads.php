<?php
// backend/scripts/find_orphan_uploads.php
declare(strict_types=1);

/**
 * Find & optionally delete orphaned uploads.
 *
 * Usage (inside DDEV):
 *   ddev exec php backend/scripts/find_orphan_uploads.php                # dry-run report
 *   ddev exec php backend/scripts/find_orphan_uploads.php --delete       # delete orphans (ask per file)
 *   ddev exec php backend/scripts/find_orphan_uploads.php --delete --yes # delete all without prompts
 *   ddev exec php backend/scripts/find_orphan_uploads.php --older=48     # only consider files older than N hours (disk)
 *   ddev exec php backend/scripts/find_orphan_uploads.php --force        # bypass TEST_MODE/ENV guard (NOT RECOMMENDED)
 *
 * Orphan types:
 *   - extra_on_disk: file exists on disk but not in DB (includes lone __thumb.webp)
 *   - missing_on_disk: DB row exists but file missing on disk
 */

if (PHP_SAPI !== 'cli') {
  fwrite(STDERR, "ERROR: Run from CLI.\n"); exit(1);
}

require __DIR__ . '/../bootstrap.php';

use Lib\{Env, Storage};

$args = $argv;
array_shift($args);
$flags = [
  'delete' => in_array('--delete', $args, true),
  'yes'    => in_array('--yes', $args, true),
  'force'  => in_array('--force', $args, true),
];
$older  = 0;
foreach ($args as $a) {
  if (preg_match('/^--older=(\d+)$/', $a, $m)) $older = (int)$m[1];
}

// Safety guard (same spirit as your cleanup script)
$envOk   = (int)Env::get('TEST_MODE', 0) === 1
        || str_contains(strtolower((string)Env::get('ENV', 'production')), 'test');
if (!$envOk && !$flags['force']) {
  fwrite(STDERR, "Refusing to run destructive scanner outside test env. Use --force if you really mean it.\n");
  exit(1);
}

$deps = dependencies();
$db   = $deps['db'];

$uploadDir = rtrim(Storage::getUploadDir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$tmpDir    = $uploadDir . '_tmp' . DIRECTORY_SEPARATOR;

// 1) Build DB -> files set
$dbRows = $db->query("SELECT stored_filename FROM files");
$dbSet  = [];
foreach ($dbRows as $r) {
  $sf = trim((string)$r['stored_filename']);
  if ($sf !== '') $dbSet[$sf] = true;
}

// 2) Walk disk (excluding _tmp)
$now = time();
$diskSet = [];            // relative path => full path
$extraOnDisk = [];        // orphan files present on disk (not in DB)
$loneThumbs  = [];        // __thumb.webp without __display.webp peer

$rii = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS)
);

foreach ($rii as $fileInfo) {
  /** @var SplFileInfo $fileInfo */
  if (!$fileInfo->isFile()) continue;

  $full = $fileInfo->getPathname();
  // skip _tmp
  if (str_starts_with($full, $tmpDir)) continue;

  $rel  = ltrim(str_replace($uploadDir, '', $full), DIRECTORY_SEPARATOR);
  $diskSet[$rel] = $full;

  // Age filter (only affects “extra_on_disk” detection; DB-missing is always reported)
  if ($older > 0) {
    $ageH = ($now - $fileInfo->getMTime())/3600;
    if ($ageH < $older) continue;
  }

  if (!isset($dbSet[$rel])) {
    // Might be a derived thumb
    if (preg_match('/__thumb\.webp$/i', $rel)) {
      $displayRel = preg_replace('/__thumb\.webp$/i', '__display.webp', $rel);
      if (!isset($dbSet[$displayRel]) && !isset($diskSet[$displayRel])) {
        $loneThumbs[$rel] = $full;
      } else {
        // If the display exists on disk or in DB, treat as non-orphan
        continue;
      }
    }
    $extraOnDisk[$rel] = $full;
  }
}

// 3) DB rows whose file is missing on disk
$missingOnDisk = [];
foreach ($dbSet as $rel => $_) {
  if (!isset($diskSet[$rel])) $missingOnDisk[$rel] = $uploadDir . $rel;
}

// 4) Report
echo "Scan complete.\n";
echo "- Uploads dir: {$uploadDir}\n";
echo "- DB files:     " . count($dbSet) . "\n";
echo "- Disk files:   " . count($diskSet) . " (excluding _tmp)\n";
if ($older > 0) echo "- Age filter:   >= {$older}h (disk extra only)\n";
echo "\n";

$extraCount   = count($extraOnDisk);
$loneCount    = count($loneThumbs);
$missingCount = count($missingOnDisk);

echo "Findings:\n";
echo "  • extra_on_disk:    {$extraCount}\n";
echo "  • lone_thumbs:      {$loneCount}\n";
echo "  • missing_on_disk:  {$missingCount}\n\n";

// Pretty print lists (shortened)
$printList = function(string $title, array $list) {
  echo $title . " (" . count($list) . ")\n";
  $i = 0;
  foreach ($list as $rel => $full) {
    if ($i >= 50) { echo "  ... (truncated)\n"; break; }
    echo "  - {$rel}\n";
    $i++;
  }
  echo "\n";
};

if ($extraCount)   $printList("EXTRA ON DISK", $extraOnDisk);
if ($loneCount)    $printList("LONE THUMBS", $loneThumbs);
if ($missingCount) $printList("MISSING ON DISK (DB rows)", $missingOnDisk);

// 5) Deletion (only extra_on_disk + lone_thumbs are safe to delete from disk)
//     DB “missing_on_disk” needs app-side fix; we do not delete DB rows here.
if ($flags['delete']) {
  $toDelete = $extraOnDisk + $loneThumbs;
  if (empty($toDelete)) {
    echo "Nothing to delete.\n";
    exit(0);
  }

  echo "About to delete " . count($toDelete) . " file(s) from disk.\n";
  if (!$flags['yes']) {
    fwrite(STDOUT, "Proceed? [y/N] ");
    $ans = strtolower(trim((string)fgets(STDIN)));
    if ($ans !== 'y' && $ans !== 'yes') {
      echo "Aborted.\n";
      exit(0);
    }
  }

  $deleted = 0;
  foreach ($toDelete as $rel => $full) {
    if (is_file($full)) {
      if (@unlink($full)) {
        $deleted++;
        echo "Deleted: {$rel}\n";
      } else {
        echo "FAILED:  {$rel}\n";
      }
    }
  }
  echo "Done. Deleted {$deleted}/" . count($toDelete) . " files.\n";
} else {
  echo "Dry-run only. Re-run with --delete (and optionally --yes) to remove extra files from disk.\n";
}

