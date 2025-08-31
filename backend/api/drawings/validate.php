<?php
// backend/api/drawings/validate.php
declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
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

    // Primary: validate shapes + uniqueness, but report conflict softly
    try {
        [$notebookId, $sectionId, $page] = Drawing::validateSlotOrThrow(
            $db,
            $notebookId,
            $sectionId,
            $page
            // no exclude id here — this endpoint validates a prospective slot
        );
        $primary = ['available' => true, 'taken' => false];
    } catch (\RuntimeException $e) {
        if ($e->getCode() === 409) {
            // Soft: slot is taken
            // Still normalize the shapes so neighbor analysis has correct ids/pages
            $notebookId = Validation::notebook($notebookId, $db);
            $sectionId  = Validation::section($sectionId, $notebookId, $db);
            $page       = Validation::page($page, $notebookId, $db);

            $primary = ['available' => false, 'taken' => true];
        } else {
            throw $e;
        }
    }

    // Neighbors (structured + non-throwing)
    $analysis = Drawing::analyzeNeighbors($db, $notebookId, $sectionId, $neighbors);

    ApiResponse::success([
        'primary'   => $primary,
        'neighbors' => [
            'valid'     => $analysis['valid'],
            'warnings'  => $analysis['warnings'],
            // Optional: expose accepted list if useful to the UI
            'accepted'  => $analysis['accepted'],
        ],
    ]);

} catch (\InvalidArgumentException $e) {
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (\Throwable $e) {
    error_log('[drawings/validate] ' . $e->getMessage());
    ApiResponse::error('Validation failed', 400);
}
