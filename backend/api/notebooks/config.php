<?php
// backend/api/notebooks/config.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;

try {
	$db = dependencies()['db'];

	$notebooks = $db->query("
		SELECT 
			n.notebook_id AS id,
			n.name,
			n.pages,
			(
				SELECT JSON_ARRAYAGG(
					JSON_OBJECT(
						'id', s.section_id,
						'label', s.label,
						'position', s.position
					) ORDER BY s.position
				)
				FROM sections s
				WHERE s.notebook_id = n.notebook_id
			) AS sections
		FROM notebooks n
		ORDER BY n.notebook_id
	");

	// Decode JSON sections into PHP arrays
	foreach ($notebooks as &$notebook) {
		$notebook['sections'] = $notebook['sections'] 
			? json_decode($notebook['sections'], true) 
			: [];
	}

	ApiResponse::success($notebooks);
} catch (Exception $e) {
	ApiResponse::error("Failed to load notebook data");
}
