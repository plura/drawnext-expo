<?php
// backend/api/drawings/create.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\{ApiResponse, Config, Drawing, Env, User, Validation};

$deps = dependencies();
$request = $deps['request'];

try {
    // 1) Validate Input Structure
    if (!isset($request['input']['drawing'])) {
        ApiResponse::error("Missing drawing data", 400);
    }

    $drawingData = $request['input']['drawing'];
    $neighbors   = $request['input']['neighbors'] ?? [];
    $uploadToken = isset($drawingData['upload_token']) && is_string($drawingData['upload_token'])
        ? trim($drawingData['upload_token'])
        : null;

    // XOR guard: exactly one of { upload_token, drawing file } must be present
    $hasFile  = isset($request['files']['drawing']) && is_array($request['files']['drawing']);
    $hasToken = is_string($uploadToken) && $uploadToken !== '';
    if ($hasFile && $hasToken) {
        ApiResponse::validationError(['error' => 'Provide either upload_token or drawing file, not both']);
    }
    if (!$hasFile && !$hasToken) {
        ApiResponse::error('No image provided (upload_token or drawing file required)', 400);
    }

    // 2) Validate Core Fields presence
    $required = ['email', 'notebook_id', 'section_id', 'page'];
    foreach ($required as $field) {
        if (!isset($drawingData[$field]) || $drawingData[$field] === '') {
            ApiResponse::error("Missing required field: drawing.$field", 400);
        }
    }

    // 2a) Email format only (no DB existence check here)
    $email = Validation::email((string)$drawingData['email']);

    // 3) Resolve (or register) user according to config
    $userId = User::resolveUserId(
        $deps['db'],
        $email,
        Config::get('submissions.allow_registration'),
        Config::get('users.auth_method')
    );

    // 4) Validate Slot Availability (app-side check; also add DB unique index)
    if (Drawing::isSlotTaken(
        $deps['db'],
        (int)$drawingData['notebook_id'],
        (int)$drawingData['section_id'],
        (int)$drawingData['page']
    )) {
        ApiResponse::conflict("Drawing slot already taken");
    }

    // 5) Create Drawing (includes file handling & neighbor saves in a transaction)
    $drawing = new Drawing($deps['db']);
    $drawing->create(
        userId: $userId,
        notebookId: Validation::notebook((int)$drawingData['notebook_id'], $deps['db']),
        sectionId:  Validation::section((int)$drawingData['section_id'], (int)$drawingData['notebook_id'], $deps['db']),
        page:       Validation::page((int)$drawingData['page'], (int)$drawingData['notebook_id'], $deps['db']),
        uploadedFile: $request['files']['drawing'] ?? null,    // legacy single-shot path
        neighbors:    $neighbors,
        isTest:       (bool)Env::get('TEST_MODE', false),
        uploadToken:  $uploadToken                             // two-phase path (optional)
    );

    ApiResponse::success($drawing->toApiResponse());

} catch (\InvalidArgumentException $e) {
    // Validation-style issues (bad format, invalid notebook/section/page, etc.)
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (\RuntimeException $e) {
    // Business logic/runtime issues (e.g., registrations disabled)
    ApiResponse::error($e->getMessage(), 400);
} catch (\Throwable $e) {
    // Unknown/unexpected
    error_log("Drawing creation failed: " . $e->getMessage());
    ApiResponse::error("Internal server error", 500);
}
