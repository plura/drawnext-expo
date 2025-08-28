<?php
// backend/api/drawings/validate.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Config;
use Lib\Validation;
use Lib\Drawing;

try {
	$deps = dependencies();
	$db   = $deps['db'];
	$req  = $deps['request'];

	$input = $req['input'] ?? [];

	// Required fields
	$notebookId = isset($input['notebook_id']) ? (int)$input['notebook_id'] : 0;
	$sectionId  = isset($input['section_id'])  ? (int)$input['section_id']  : 0;
	$page       = isset($input['page'])        ? (int)$input['page']        : 0;
	$neighbors  = isset($input['neighbors'])   && is_array($input['neighbors']) ? $input['neighbors'] : [];

	// Validate base shapes
	$notebookId = Validation::notebook($notebookId, $db);
	$sectionId  = Validation::section($sectionId, $notebookId, $db);
	$page       = Validation::page($page, $notebookId, $db);

	// Primary availability
	$primaryTaken = Drawing::isSlotTaken($db, $notebookId, $sectionId, $page);
	$primary = [
		'available' => !$primaryTaken,
		'taken'     => $primaryTaken
	];

	// Neighbors: validate shape + existence (only for entries that have a page)
	$outNeighbors = [];
	foreach ($neighbors as $n) {
		if (!isset($n['section_id']) || !isset($n['page'])) {
			continue; // ignore incomplete rows
		}
		$nSection = Validation::section((int)$n['section_id'], $notebookId, $db);
		$nPage    = Validation::page((int)$n['page'], $notebookId, $db);

		if ($nSection === $sectionId) {
			// You can choose to soft-flag this instead of hard error; here we soft-flag
			$outNeighbors[] = [
				'section_id' => $nSection,
				'page'       => $nPage,
				'exists'     => false,
				'warning'    => 'neighbor_cannot_be_primary_section'
			];
			continue;
		}

		$exists = Drawing::isSlotTaken($db, $notebookId, $nSection, $nPage);
		$outNeighbors[] = [
			'section_id' => $nSection,
			'page'       => $nPage,
			'exists'     => $exists
		];
	}

	ApiResponse::success([
		'primary'   => $primary,
		'neighbors' => $outNeighbors
	]);
} catch (\InvalidArgumentException $e) {
	ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (\Throwable $e) {
	error_log('[drawings/validate] ' . $e->getMessage());
	ApiResponse::error('Validation failed', 400);
}
