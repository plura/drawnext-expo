<?php
// backend/api/notebooks/config.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Config;

try {
    $deps = dependencies();
    $db   = $deps['db'];

    // Fallback pages from config (e.g., notebooks.pages.fallback_count = 10)
    $fallback = (int) Config::get('notebooks.pages.fallback_count');

    $sql = "
        SELECT
            n.notebook_id AS id,
            n.name,
            COALESCE(n.pages, ?) AS pages,
            COALESCE(
                (
                    SELECT CONCAT(
                        '[',
                        GROUP_CONCAT(
                            JSON_OBJECT(
                                'id', s.section_id,
                                'label', s.label,
                                'position', s.`position`
                            )
                            ORDER BY s.`position`
                            SEPARATOR ','
                        ),
                        ']'
                    )
                    FROM sections s
                    WHERE s.notebook_id = n.notebook_id
                ),
                JSON_ARRAY()  -- return [] when no sections
            ) AS sections
        FROM notebooks n
        ORDER BY n.notebook_id";

    $rows = $db->query($sql, [$fallback]);


    // Decode JSON sections and coerce types
    foreach ($rows as &$nb) {
        $nb['pages']    = (int) $nb['pages'];
        $nb['sections'] = $nb['sections'] ? json_decode($nb['sections'], true) : [];
        if (is_array($nb['sections'])) {
            foreach ($nb['sections'] as &$s) {
                $s['id']       = (int) $s['id'];
                $s['position'] = (int) $s['position'];
            }
            unset($s);
        }
    }
    unset($nb);

    ApiResponse::success($rows, []);
} catch (Throwable $e) {
    error_log("[notebooks/config] " . $e->getMessage());
    ApiResponse::error("Failed to load notebook data");
}
