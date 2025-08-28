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

	// Read global aspect ratio from config (CSV -> ['1','1'])
	$ar  = (array) Config::get('images.aspect_ratio');
	$arw = max(1, (int)($ar[0] ?? 1));
	$arh = max(1, (int)($ar[1] ?? 1));

	$sql = "SELECT
		n.notebook_id AS id,
		n.title,                 -- renamed from name
		n.subtitle,
		n.description,
		n.color_bg,
		n.color_text,
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
			JSON_ARRAY()
		) AS sections
	FROM notebooks n
	ORDER BY n.notebook_id";

	$rows = $db->query($sql, [$fallback]);

	// Decode JSON sections, coerce types, and attach global aspect
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

		// Attach global aspect (w/h) to each notebook
		$nb['aspect'] = ['w' => $arw, 'h' => $arh];
	}
	unset($nb);

	ApiResponse::success($rows, []);
} catch (\Throwable $e) {
	error_log("[notebooks/config] " . $e->getMessage());
	ApiResponse::error("Failed to load notebook data");
}
