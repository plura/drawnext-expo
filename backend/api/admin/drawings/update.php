<?php
// backend/api/admin/drawings/update.php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Drawing;
use Lib\Validation;

function http_status_from_exception(\Throwable $e): int {
    if ($e instanceof \PDOException) {
        return 500;
    }
    $code = (int) $e->getCode();
    if ($code >= 400 && $code <= 599) {
        return $code;
    }
    return ($e instanceof \RuntimeException) ? 400 : 500;
}

try {
    error_log('[admin/drawings/update] start');

    $deps = dependencies();
    $db   = $deps['db'];
    $req  = $deps['request'];

    $input = $req['input'] ?? $req ?? [];

    $drawingId  = isset($input['drawing_id'])  ? (int) $input['drawing_id']  : 0;
    $notebookId = isset($input['notebook_id']) ? (int) $input['notebook_id'] : 0;
    $sectionId  = isset($input['section_id'])  ? (int) $input['section_id']  : 0;
    $page       = isset($input['page'])        ? (int) $input['page']        : 0;
    $neighbors  = (isset($input['neighbors']) && is_array($input['neighbors'])) ? $input['neighbors'] : [];

    if ($drawingId <= 0) {
        ApiResponse::validationError(['errors' => ['drawing_id' => 'Missing or invalid drawing_id']]);
    }

    $notebookId = Validation::notebook($notebookId, $db);
    $sectionId  = Validation::section($sectionId, $notebookId, $db);
    $page       = Validation::page($page, $notebookId, $db);

    Drawing::validateSlotOrThrow($db, $notebookId, $sectionId, $page, $drawingId);

    $analysis = Drawing::analyzeNeighbors($db, $notebookId, $sectionId, $neighbors);
    if (!$analysis['valid']) {
        ApiResponse::validationError(['neighbors' => $analysis['warnings']]);
    }

    $db->beginTransaction();

    // 🔧 Removed `modified_at = NOW()` because the column doesn't exist in your schema
    $db->execute(
        "UPDATE drawings
           SET notebook_id = ?, section_id = ?, page = ?
         WHERE drawing_id = ?",
        [$notebookId, $sectionId, $page, $drawingId]
    );

    $db->execute("DELETE FROM drawing_neighbors WHERE drawing_id = ?", [$drawingId]);
    foreach ($neighbors as $n) {
        $db->execute(
            "INSERT INTO drawing_neighbors (drawing_id, neighbor_section_id, neighbor_page)
             VALUES (?, ?, ?)",
            [$drawingId, (int)$n['section_id'], (int)$n['page']]
        );
    }

    $db->commit();

    error_log('[admin/drawings/update] success drawing_id=' . $drawingId);

    ApiResponse::success([
        'drawing_id' => $drawingId,
        'updated'    => true,
        'neighbors'  => $neighbors
    ]);

} catch (\InvalidArgumentException $e) {
    $db?->rollBack();
    error_log('[admin/drawings/update] INVALID: ' . $e::class . ' code=' . $e->getCode() . ' msg=' . $e->getMessage());
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (\RuntimeException $e) {
    $db?->rollBack();
    $code = http_status_from_exception($e);
    error_log('[admin/drawings/update] RUNTIME: ' . $e::class . ' code=' . $e->getCode() . ' http=' . $code . ' msg=' . $e->getMessage());
    ApiResponse::error($e->getMessage(), $code);
} catch (\Throwable $e) {
    $db?->rollBack();
    $code = http_status_from_exception($e);
    error_log('[admin/drawings/update] FATAL: ' . $e::class . ' code=' . $e->getCode() . ' http=' . $code . ' msg=' . $e->getMessage());
    ApiResponse::error('Update failed', $code);
}
