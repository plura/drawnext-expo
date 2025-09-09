<?php
// backend/api/drawings/related.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Config;

try {
    $deps = dependencies();
    $db   = $deps['db'];

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
        ApiResponse::error('Method not allowed', 405);
    }

    // -----------------------------
    // Inputs
    // -----------------------------
    $limit  = isset($_GET['limit'])  ? max(1, min((int)$_GET['limit'], 200)) : 30;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset'])         : 0;

    $drawingId  = isset($_GET['drawing_id'])  ? (int)$_GET['drawing_id'] : 0;
    $notebookId = isset($_GET['notebook_id']) ? (int)$_GET['notebook_id'] : 0;
    $sectionId  = isset($_GET['section_id'])  ? (int)$_GET['section_id']  : 0;
    $page       = isset($_GET['page'])        ? (int)$_GET['page']        : 0;

    // Resolve tuple from drawing_id if provided
    if ($drawingId > 0) {
        $row = $db->querySingle(
            'SELECT notebook_id, section_id, page FROM drawings WHERE drawing_id = ?',
            [$drawingId]
        );
        if (!$row) ApiResponse::error('Drawing not found', 404);
        $notebookId = (int)$row['notebook_id'];
        $sectionId  = (int)$row['section_id'];
        $page       = (int)$row['page'];
    }

    if ($notebookId <= 0 || $sectionId <= 0 || $page <= 0) {
        ApiResponse::error('Missing notebook_id/section_id/page (or drawing_id)', 400);
    }

    // -----------------------------
    // expand flags (keep parity with /drawings/list)
    // -----------------------------
    $expand = isset($_GET['expand'])
        ? array_filter(array_map('trim', explode(',', (string)$_GET['expand'])))
        : [];
    $wantUser      = in_array('user', $expand, true);
    $wantNeighbors = in_array('neighbors', $expand, true);
    $wantMeta      = in_array('meta', $expand, true);
    $wantThumb     = in_array('thumb', $expand, true);
    $wantLabels    = in_array('labels', $expand, true) || in_array('section_label', $expand, true);

    // -----------------------------
    // Query: rows that reference the target (section,page) as a neighbor
    // -----------------------------
    $select = [
        'd.drawing_id',
        'd.notebook_id',
        'd.section_id',
        'd.page',
        'd.created_at',
        'f.stored_filename',
        // keep parity with /drawings/list → expose section_position on the MAIN row
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
        'INNER JOIN drawing_neighbors rn ON rn.drawing_id = d.drawing_id',
        'LEFT JOIN files f ON f.drawing_id = d.drawing_id',
        'LEFT JOIN sections s_main ON s_main.section_id = d.section_id',
    ];
    if ($wantUser) {
        $select[] = 'u.email AS user_email';
        $joins[]  = 'INNER JOIN users u ON u.user_id = d.user_id';
    }

    $selectList = implode(',', $select);
    $joinSql    = implode("\n", $joins);

    // Enforce same notebook as the target; and the neighbor tuple
    $where  = 'WHERE rn.neighbor_section_id = ? AND rn.neighbor_page = ? AND d.notebook_id = ?';
    $params = [$sectionId, $page, $notebookId];

    $orderSql = 'ORDER BY d.created_at DESC, d.drawing_id DESC';

    // NOTE: LIMIT/OFFSET interpolated from validated ints (PDO cannot bind them with emulation off).
    $sql = "SELECT {$selectList}
FROM drawings d
{$joinSql}
{$where}
{$orderSql}
LIMIT {$limit} OFFSET {$offset}";

    $rows = $db->query($sql, $params);

    // -----------------------------
    // Build URLs (same approach as /drawings/list)
    // -----------------------------
    $uploadsDir = trim((string)Config::get('uploads.directory'), '/');
    $prefix = '/' . ($uploadsDir ?: 'uploads') . '/'; // preview/thumb URLs use this prefix. :contentReference[oaicite:1]{index=1}

    $data = [];
    $ids  = [];

    foreach ($rows as $r) {
        $did = (int)$r['drawing_id'];

        $item = [
            'drawing_id'       => $did,
            'notebook_id'      => (int)$r['notebook_id'],
            'section_id'       => (int)$r['section_id'],
            'section_position' => isset($r['section_position']) ? (int)$r['section_position'] : null,
            'page'             => (int)$r['page'],
            'created_at'       => $r['created_at'],
            'preview_url'      => !empty($r['stored_filename']) ? $prefix . rawurlencode($r['stored_filename']) : null,
        ];

        if ($wantLabels) {
            $item['section_label'] = $r['section_label'] ?? null;
        }
        if ($wantUser) {
            $item['user_email'] = $r['user_email'] ?? null;
        }
        if ($wantMeta) {
            $item['image'] = [
                'width'    => isset($r['width']) ? (int)$r['width'] : null,
                'height'   => isset($r['height']) ? (int)$r['height'] : null,
                'mime'     => $r['mime_type'] ?? null,
                'filesize' => isset($r['filesize']) ? (int)$r['filesize'] : null,
            ];
        }

        if ($wantThumb && !empty($r['stored_filename']) && preg_match('/__display\.webp$/i', $r['stored_filename'])) {
            $thumb = preg_replace('/__display\.webp$/i', '__thumb.webp', $r['stored_filename']);
            $item['thumb_url'] = $prefix . rawurlencode($thumb);
        } else {
            $item['thumb_url'] = null;
        }

        $data[] = $item;
        $ids[]  = $did;
    }

    // -----------------------------
    // Optional neighbors expansion (parity with /drawings/list)
    // -----------------------------
    if ($wantNeighbors && $ids) {
        $ph = implode(',', array_fill(0, count($ids), '?'));

        // Batch select neighbors for all returned drawings,
        // mapping each (child) neighbor to its main drawing.
        $neiSelect = [
            'dn.drawing_id',
            'dn.neighbor_section_id AS section_id',
            'dn.neighbor_page AS page',
            's.position AS section_position',
            'nd.drawing_id AS neighbor_drawing_id',
            'nf.stored_filename AS neighbor_stored_filename',
        ];
        if ($wantLabels) {
            $neiSelect[] = 's.label AS section_label';
        }

        $neiSql = "SELECT " . implode(',', $neiSelect) . "
FROM drawing_neighbors dn
INNER JOIN drawings d_main ON d_main.drawing_id = dn.drawing_id
INNER JOIN sections s ON s.section_id = dn.neighbor_section_id
LEFT JOIN drawings nd
       ON nd.notebook_id = d_main.notebook_id
      AND nd.section_id  = dn.neighbor_section_id
      AND nd.page        = dn.neighbor_page
LEFT JOIN files nf ON nf.drawing_id = nd.drawing_id
WHERE dn.drawing_id IN ($ph)
ORDER BY s.position ASC, dn.neighbor_page ASC";

        $neiRows = $db->query($neiSql, $ids);

        // index by main drawing_id
        $byMain = [];
        foreach ($neiRows as $n) {
            $mainId = (int)$n['drawing_id'];
            $entry = [
                'section_id'       => (int)$n['section_id'],
                'page'             => (int)$n['page'],
                'section_position' => isset($n['section_position']) ? (int)$n['section_position'] : null,
                'neighbor_drawing_id' => isset($n['neighbor_drawing_id']) ? (int)$n['neighbor_drawing_id'] : null,
            ];
            if ($wantLabels) {
                $entry['section_label'] = $n['section_label'] ?? null;
            }
            // If the neighbor has a stored file, expose a thumb_url like /list does.
            if ($wantThumb && !empty($n['neighbor_stored_filename']) && preg_match('/__display\.webp$/i', $n['neighbor_stored_filename'])) {
                $entry['thumb_url'] = $prefix . rawurlencode(preg_replace('/__display\.webp$/i', '__thumb.webp', $n['neighbor_stored_filename']));
            } else {
                $entry['thumb_url'] = null;
            }

            $byMain[$mainId][] = $entry;
        }

        // attach to each item
        foreach ($data as &$item) {
            $item['neighbors'] = $byMain[$item['drawing_id']] ?? [];
        }
        unset($item);
    }

    // -----------------------------
    // Meta + response
    // -----------------------------
    $hasMore = count($rows) === $limit;
    ApiResponse::success($data, [
        'limit'       => $limit,
        'offset'      => $offset,
        'count'       => count($rows),
        'next_offset' => $offset + $limit,
        'has_more'    => $hasMore,
    ]);

} catch (\Throwable $e) {
    error_log('[drawings/related] ' . $e->getMessage());
    ApiResponse::error('Failed to load related drawings', 500);
}
