<?php
// backend/api/drawings/list.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Config;

try {
    $deps = dependencies();
    $db   = $deps['db'];

    // --- Query params (GET) ---
    $limit  = isset($_GET['limit'])  ? max(1, min((int)$_GET['limit'], 50)) : 20;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    $filters = [];
    $params  = [];

    if (isset($_GET['notebook_id']) && $_GET['notebook_id'] !== '') {
        $filters[] = 'd.notebook_id = ?';
        $params[]  = (int)$_GET['notebook_id'];
    }
    if (isset($_GET['section_id']) && $_GET['section_id'] !== '') {
        $filters[] = 'd.section_id = ?';
        $params[]  = (int)$_GET['section_id'];
    }
    if (isset($_GET['page']) && $_GET['page'] !== '') {
        $filters[] = 'd.page = ?';
        $params[]  = (int)$_GET['page'];
    }

    $where = $filters ? ('WHERE ' . implode(' AND ', $filters)) : '';

    // NOTE: MySQL doesn't allow binding LIMIT/OFFSET when emulation is off, so we safely interpolate validated ints.
    $sql = "
        SELECT
            d.drawing_id,
            d.notebook_id,
            d.section_id,
            d.page,
            d.created_at,
            f.stored_filename
        FROM drawings d
        LEFT JOIN files f ON f.drawing_id = d.drawing_id
        $where
        ORDER BY d.created_at DESC, d.drawing_id DESC
        LIMIT $limit OFFSET $offset
    ";

    $rows = $db->query($sql, $params);

    // Compute preview URL based on configured uploads directory
    $uploadsDir = trim((string)Config::get('uploads.directory'), '/');
    $prefix = '/' . ($uploadsDir ?: 'uploads') . '/';

    $data = [];
    foreach ($rows as $r) {
        $item = [
            'drawing_id'  => (int)$r['drawing_id'],
            'notebook_id' => (int)$r['notebook_id'],
            'section_id'  => (int)$r['section_id'],
            'page'        => (int)$r['page'],
            'created_at'  => $r['created_at'],
            'preview_url' => $r['stored_filename'] ? $prefix . rawurlencode($r['stored_filename']) : null,
        ];
        $data[] = $item;
    }

    // Simple has_more: fetch one extra row (optional). Here infer by count next page.
    $hasMore = count($rows) === $limit; // heuristic; fine for now
    $meta = [
        'limit'      => $limit,
        'offset'     => $offset,
        'count'      => count($rows),
        'next_offset'=> $offset + $limit,
        'has_more'   => $hasMore,
    ];

    ApiResponse::success($data, $meta);

} catch (\Throwable $e) {
    error_log('[drawings/list] ' . $e->getMessage());
    ApiResponse::error('Failed to load drawings', 500);
}
