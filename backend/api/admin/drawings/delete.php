<?php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;
use Lib\Config;

try {
  $deps = dependencies();
  $db   = $deps['db'];
  $req  = $deps['request'];

  Auth::init();
  $email = Auth::getEmail();
  if (!$email) ApiResponse::error('Not authenticated', 401);

  $row = $db->querySingle('SELECT is_admin FROM users WHERE email = ?', [$email]);
  if ((int)($row['is_admin'] ?? 0) !== 1) {
    ApiResponse::error('Forbidden', 403);
  }

  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    ApiResponse::error('Method not allowed', 405);
  }

  $input     = $req['input'] ?? [];
  $drawingId = (int)($input['drawing_id'] ?? 0);
  if ($drawingId <= 0) ApiResponse::error('Invalid drawing_id', 400);

  // Load file rows first so we can unlink from disk after delete
  $files = $db->query(
    "SELECT stored_filename FROM files WHERE drawing_id = ?",
    [$drawingId]
  );

  // Build physical paths to attempt deletion after DB ops
  $uploadsDir = trim((string)Config::get('uploads.directory'), '/');
  $prefix = '/' . ($uploadsDir ?: 'uploads') . '/';
  $projectRoot = defined('PROJECT_ROOT') ? PROJECT_ROOT : realpath(__DIR__ . '/../../../..');
  $paths = [];
  foreach ($files as $f) {
    $sf = (string)$f['stored_filename'];
    $display = rtrim($projectRoot, DIRECTORY_SEPARATOR) . $prefix . $sf;
    $paths[$display] = true;
    if (preg_match('/__display\.webp$/i', $sf)) {
      $thumb = preg_replace('/__display\.webp$/i', '__thumb.webp', $sf);
      $thumbPath = rtrim($projectRoot, DIRECTORY_SEPARATOR) . $prefix . $thumb;
      $paths[$thumbPath] = true;
    }
  }

  // Transaction: neighbors, files, drawing
  $db->beginTransaction();
  try {
    $db->execute("DELETE FROM drawing_neighbors WHERE drawing_id = ?", [$drawingId]);
    $db->execute("DELETE FROM files WHERE drawing_id = ?", [$drawingId]);
    $affected = $db->execute("DELETE FROM drawings WHERE drawing_id = ?", [$drawingId]);

    if ($affected === false) {
      throw new \RuntimeException('Delete failed');
    }

    $db->commit();
  } catch (\Throwable $e) {
    $db->rollBack();
    throw $e;
  }

  // Best-effort unlink on disk (ignore errors)
  foreach (array_keys($paths) as $p) {
    if (is_file($p)) {
      @unlink($p);
    }
  }

  ApiResponse::success(['ok' => true]);

} catch (\Throwable $e) {
  error_log('[admin/drawings/delete] ' . $e->getMessage());
  ApiResponse::error('Delete failed', 400);
}
