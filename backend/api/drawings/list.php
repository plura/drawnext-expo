<?php
// api/drawings/list.php
// Filters: notebook_id, section_id, page
// Pagination: limit (1..50), offset (>=0)
// Expansions via `expand` (CSV):
//   user       → include user_email (main + neighbors)
//   neighbors  → include neighbors array
//   labels     → include section_label (main + neighbors)
//   meta       → include image meta on main drawing
//   thumb      → include thumb_url (main + neighbors when a WebP thumb exists)
// Extras:
//   rand=1     → randomize order (server-side). When enabled, offset is ignored.

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

    // Server-side randomize toggle
    $rand = isset($_GET['rand']) && $_GET['rand'] !== '' && $_GET['rand'] !== '0';

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

    $hasNeighbors = isset($_GET['has_neighbors']) && $_GET['has_neighbors'] !== '' && $_GET['has_neighbors'] !== '0';
    if ($hasNeighbors) {
        $filters[] = 'EXISTS (SELECT 1 FROM drawing_neighbors dn WHERE dn.drawing_id = d.drawing_id)';
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
        // always provide section_position for MAIN row
        's_main.position AS section_position',
    ];

    if ($wantMeta) {
        $select[] = 'f.width';
        $select[] = 'f.height';
        $select[] = 'f.mime_type';
        $select[] = 'f.filesize';
    }
    if ($wantLabels) {
        $select[] = 's_main.label AS section_label';
    }

    $joins = [
        'LEFT JOIN files f ON f.drawing_id = d.drawing_id',
        // join sections to expose section_position (and possibly label)
        'LEFT JOIN sections s_main ON s_main.section_id = d.section_id',
    ];

    if ($wantUser) {
        $select[] = 'u.email AS user_email';
        $joins[]  = 'INNER JOIN users u ON u.user_id = d.user_id';
    }

    $selectList = implode(',', $select);
    $joinSql    = $joins ? implode("\n", $joins) : '';

    // Decide ordering
    // NOTE: OFFSET with RAND() has little meaning; we ignore offset when $rand is true.
    $orderSql = $rand
        ? 'ORDER BY RAND()'
        : 'ORDER BY d.created_at DESC, d.drawing_id DESC';

    if ($rand) {
        $offset = 0;
    }

    // NOTE: MySQL can't bind LIMIT/OFFSET with emulation off; ints are validated above and interpolated safely.
    $sql = "SELECT {$selectList}
FROM drawings d
{$joinSql}
{$where}
{$orderSql}
LIMIT {$limit} OFFSET {$offset}";

    $rows = $db->query($sql, $params);

    // Build URL prefix for files
    $uploadsDir = trim((string)Config::get('uploads.directory'), '/');
    $prefix = '/' . ($uploadsDir ?: 'uploads') . '/';

    // Prepare base data
    $data = [];
    $ids  = [];
    $mainNotebookById = []; // drawing_id => notebook_id (used by neighbors join strategy if needed)

    foreach ($rows as $r) {
        $did = (int)$r['drawing_id'];
        $nid = (int)$r['notebook_id'];

        $item = [
            'drawing_id'       => $did,
            'notebook_id'      => $nid,
            'section_id'       => (int)$r['section_id'],
            'section_position' => isset($r['section_position']) ? (int)$r['section_position'] : null,
            'page'             => (int)$r['page'],
            'created_at'       => $r['created_at'],
            // preview_url points to stored file path as-is (e.g., *__display.webp)
            'preview_url'      => !empty($r['stored_filename']) ? $prefix . rawurlencode($r['stored_filename']) : null,
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
        $ids[]  = $did;
        $mainNotebookById[$did] = $nid;
    }

    // Neighbors expansion
    if ($wantNeighbors && $ids) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $selectNei = [
            'dn.drawing_id',
            'dn.neighbor_section_id AS section_id',
            'dn.neighbor_page AS page',
            's.position AS section_position',
            'nf.stored_filename AS neighbor_stored_filename',
            'nd.drawing_id AS neighbor_drawing_id',
        ];
        $joinsNei = [
            'INNER JOIN drawings d_main ON d_main.drawing_id = dn.drawing_id',
            'INNER JOIN sections s ON s.section_id = dn.neighbor_section_id',
            'LEFT JOIN drawings nd
                 ON nd.notebook_id = d_main.notebook_id
                AND nd.section_id  = dn.neighbor_section_id
                AND nd.page        = dn.neighbor_page',
            'LEFT JOIN files nf ON nf.drawing_id = nd.drawing_id',
        ];
        if ($wantLabels) {
            $selectNei[] = 's.label AS section_label';
        }
        if ($wantUser) {
            $selectNei[] = 'u.email AS user_email';
            $joinsNei[]  = 'LEFT JOIN users u ON u.user_id = nd.user_id';
        }

        $neiSql = "SELECT " . implode(',', $selectNei) . "
            FROM drawing_neighbors dn
            " . implode("\n", $joinsNei) . "
            WHERE dn.drawing_id IN ({$placeholders})";

        $neighbors = $db->query($neiSql, $ids);

        $map = [];
        foreach ($neighbors as $n) {
            $did = (int)$n['drawing_id'];
            if (!isset($map[$did])) $map[$did] = [];

            $entry = [
                'drawing_id'       => isset($n['neighbor_drawing_id']) ? (int)$n['neighbor_drawing_id'] : null,
                'section_id'       => (int)$n['section_id'],
                'section_position' => isset($n['section_position']) ? (int)$n['section_position'] : null,
                'page'             => (int)$n['page'],
                'preview_url'      => !empty($n['neighbor_stored_filename'])
                    ? $prefix . rawurlencode($n['neighbor_stored_filename'])
                    : null,
            ];
            if ($wantLabels) {
                $entry['section_label'] = $n['section_label'] ?? null;
            }
            if ($wantUser) {
                $entry['user_email'] = $n['user_email'] ?? null;
            }
            if ($wantThumb && !empty($n['neighbor_stored_filename']) && preg_match('/__display\\.webp$/i', $n['neighbor_stored_filename'])) {
                $thumb = preg_replace('/__display\\.webp$/i', '__thumb.webp', $n['neighbor_stored_filename']);
                $entry['thumb_url'] = $prefix . rawurlencode($thumb);
            } else {
                $entry['thumb_url'] = null;
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
        'random'      => $rand ? 1 : 0,
    ];

    ApiResponse::success($data, $meta);

} catch (\Throwable $e) {
    error_log('[drawings/list] ' . $e->getMessage());
    ApiResponse::error('Failed to load drawings', 500);
}
