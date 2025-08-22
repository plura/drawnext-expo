<?php
// backend/api/images/temp.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\FileHandler;
use Lib\Env;

try {
	// Ensure dependencies are initialized (DB + Config)
	dependencies();

	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		ApiResponse::error('Method not allowed', 405);
	}

	if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
		ApiResponse::error('No image uploaded', 400);
	}

	$isTest = (bool) Env::get('TEST_MODE', false);
	$result = FileHandler::saveTempUpload($_FILES['image'], $isTest);

	ApiResponse::success($result);
} catch (\Throwable $e) {
	// Return a safe error; details are logged by PHP error log
	ApiResponse::error($e->getMessage(), 400);
}
