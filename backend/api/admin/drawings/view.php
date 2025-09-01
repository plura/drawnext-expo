<?php
//backend/api/admin/drawings/view.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;
use Lib\Config;

try {
  $deps = dependencies();
  $db   = $deps['db'];

  Auth::init();
  $email = Auth::getEmail();
  if (!$email) ApiResponse::error('Not authenticated', 401);

  // Ensure admin
  $row = $db->querySingle('SELECT is_admin FROM users WHERE email = ?', [$email]);
  if ((int)($row['is_admin'] ?? 0) !== 1) {
    ApiResponse::error('Forbidden', 403);
  }

  $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
  if ($id <= 0) ApiResponse::error('Missing or invalid id', 400);

  // Core drawing + file + user + section label
  $sql = "
  SELECT
    d.drawing_id,
    d.notebook_id,
    d.section_id,
    d.page,
    d.created_at,
    u.email AS user_email,
    f.stored_filename,
    f.width, f.height, f.mime_type, f.filesize,
    s.label AS section_label
  FROM drawings d
    INNER JOIN users u ON u.user_id = d.user_id
    LEFT JOIN files f ON f.drawing_id = d.drawing_id
    LEFT JOIN sections s ON s.section_id = d.section_id
  WHERE d.drawing_id = ?";

  $base = $db->querySingle($sql, [$id]);
  if (!$base) ApiResponse::error('Not found', 404);

  // Neighbors (with labels)
  $neighbors = $db->query(
    "SELECT dn.neighbor_section_id AS section_id,
            dn.neighbor_page       AS page,
            s.label                AS section_label
     FROM drawing_neighbors dn
     LEFT JOIN sections s ON s.section_id = dn.neighbor_section_id
     WHERE dn.drawing_id = ?
     ORDER BY s.position ASC, dn.neighbor_page ASC",
    [$id]
  );

  // Build URLs like /api/drawings/list does
  $uploadsDir = trim((string)Config::get('uploads.directory'), '/');
  $prefix = '/' . ($uploadsDir ?: 'uploads') . '/';
  $preview = !empty($base['stored_filename']) ? ($prefix . rawurlencode($base['stored_filename'])) : null;
  $thumb = null;
  if ($preview && preg_match('/__display\.webp$/i', $base['stored_filename'])) {
    $thumb = $prefix . rawurlencode(preg_replace('/__display\.webp$/i', '__thumb.webp', $base['stored_filename']));
  }

  ApiResponse::success([
    'drawing_id'  => (int)$base['drawing_id'],
    'notebook_id' => (int)$base['notebook_id'],
    'section_id'  => (int)$base['section_id'],
    'page'        => (int)$base['page'],
    'created_at'  => $base['created_at'],
    'user_email'  => $base['user_email'] ?? null,
    'section_label' => $base['section_label'] ?? null,
    'preview_url' => $preview,
    'thumb_url'   => $thumb,
    'image' => [
      'width'    => isset($base['width']) ? (int)$base['width'] : null,
      'height'   => isset($base['height']) ? (int)$base['height'] : null,
      'mime'     => $base['mime_type'] ?? null,
      'filesize' => isset($base['filesize']) ? (int)$base['filesize'] : null,
    ],
    'neighbors' => array_map(static function($n) {
      return [
        'section_id'    => (int)$n['section_id'],
        'page'          => (int)$n['page'],
        'section_label' => $n['section_label'] ?? null,
      ];
    }, $neighbors ?: []),
  ]);

} catch (\Throwable $e) {
  error_log('[admin/drawings/view] ' . $e->getMessage());
  ApiResponse::error('Failed to load drawing', 400);
}
