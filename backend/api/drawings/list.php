<?php
// api/drawings/list.php (enhanced, backward compatible)
// - Filters: notebook_id, section_id, page
// - Pagination: limit (1..50), offset (>=0)
// - Expansions via `expand` (CSV):
//     user       → include user_email
//     neighbors  → include neighbors: [{ section_id, page }]
//     labels     → include section_label for main drawing + neighbors
//     meta       → include image meta: {width, height, mime, filesize}
//     thumb      → include thumb_url when a WebP thumb exists

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

    // Expansions
    $expand = isset($_GET['expand'])
        ? array_filter(array_map('trim', explode(',', (string)$_GET['expand'])))
        : [];
    $wantUser      = in_array('user', $expand, true);
    $wantNeighbors = in_array('neighbors', $expand, true);
    $wantMeta      = in_array('meta', $expand, true);
    $wantThumb     = in_array('thumb', $expand, true);
    $wantLabels    = in_array('labels', $expand, true) || in_array('section_label', $expand, true);

    // Base SELECT (files joined for preview + optional meta)
    $select = [
        'd.drawing_id',
        'd.notebook_id',
        'd.section_id',
        'd.page',
        'd.created_at',
        'f.stored_filename',
    ];

    if ($wantMeta) {
        $select[] = 'f.width';
        $select[] = 'f.height';
        $select[] = 'f.mime_type';
        $select[] = 'f.filesize';
    }

    $joins = ['LEFT JOIN files f ON f.drawing_id = d.drawing_id'];

    if ($wantUser) {
        $select[] = 'u.email AS user_email';
        $joins[]  = 'INNER JOIN users u ON u.user_id = d.user_id';
    }

    if ($wantLabels) {
        $select[] = 's_main.label AS section_label';
        $joins[]  = 'LEFT JOIN sections s_main ON s_main.section_id = d.section_id';
    }

    $selectList = implode(',', $select);
    $joinSql    = $joins ? implode("\n", $joins) : '';

    // NOTE: MySQL can't bind LIMIT/OFFSET with emulation off; ints are validated above and interpolated safely.
    $sql = "SELECT {$selectList}
FROM drawings d
{$joinSql}
{$where}
ORDER BY d.created_at DESC, d.drawing_id DESC
LIMIT {$limit} OFFSET {$offset}";

    $rows = $db->query($sql, $params);

    // Build URL prefix for files
    $uploadsDir = trim((string)Config::get('uploads.directory'), '/');
    $prefix = '/' . ($uploadsDir ?: 'uploads') . '/';

    // Prepare base data
    $data = [];
    $ids  = [];

    foreach ($rows as $r) {
        $item = [
            'drawing_id'  => (int)$r['drawing_id'],
            'notebook_id' => (int)$r['notebook_id'],
            'section_id'  => (int)$r['section_id'],
            'page'        => (int)$r['page'],
            'created_at'  => $r['created_at'],
            'preview_url' => !empty($r['stored_filename']) ? $prefix . rawurlencode($r['stored_filename']) : null,
        ];

        if ($wantLabels) {
            $item['section_label'] = $r['section_label'] ?? null;
        }

        if ($wantUser) {
            $item['user_email'] = $r['user_email'] ?? null; // consider masking on public UIs
        }

        if ($wantMeta) {
            $item['image'] = [
                'width'    => isset($r['width']) ? (int)$r['width'] : null,
                'height'   => isset($r['height']) ? (int)$r['height'] : null,
                'mime'     => $r['mime_type'] ?? null,
                'filesize' => isset($r['filesize']) ? (int)$r['filesize'] : null,
            ];
        }

        if ($wantThumb && !empty($r['stored_filename']) && preg_match('/__display\\.webp$/i', $r['stored_filename'])) {
            $thumb = preg_replace('/__display\\.webp$/i', '__thumb.webp', $r['stored_filename']);
            $item['thumb_url'] = $prefix . rawurlencode($thumb);
        } else {
            $item['thumb_url'] = null; // no derivative
        }

        $data[] = $item;
        $ids[]  = (int)$r['drawing_id'];
    }

    // Neighbors expansion (single query, no N+1)
    if ($wantNeighbors && $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        if ($wantLabels) {
            $neighbors = $db->query(
                "SELECT dn.drawing_id,
       dn.neighbor_section_id AS section_id,
       s.label AS section_label,
       dn.neighbor_page AS page
FROM drawing_neighbors dn
INNER JOIN sections s ON s.section_id = dn.neighbor_section_id
WHERE dn.drawing_id IN ({$placeholders})",
                $ids
            );
        } else {
            $neighbors = $db->query(
                "SELECT drawing_id,
       neighbor_section_id AS section_id,
       neighbor_page AS page
FROM drawing_neighbors
WHERE drawing_id IN ({$placeholders})",
                $ids
            );
        }

        $map = [];
        foreach ($neighbors as $n) {
            $did = (int)$n['drawing_id'];
            if (!isset($map[$did])) $map[$did] = [];
            $entry = [
                'section_id' => (int)$n['section_id'],
                'page'       => (int)$n['page'],
            ];
            if ($wantLabels) {
                $entry['section_label'] = $n['section_label'] ?? null;
            }
            $map[$did][] = $entry;
        }

        foreach ($data as &$item) {
            $item['neighbors'] = $map[$item['drawing_id']] ?? [];
        }
        unset($item);
    }

    // Pagination meta
    $hasMore = count($rows) === $limit;
    $meta = [
        'limit'       => $limit,
        'offset'      => $offset,
        'count'       => count($rows),
        'next_offset' => $offset + $limit,
        'has_more'    => $hasMore,
    ];

    ApiResponse::success($data, $meta);

} catch (\Throwable $e) {
    error_log('[drawings/list] ' . $e->getMessage());
    ApiResponse::error('Failed to load drawings', 500);
}
