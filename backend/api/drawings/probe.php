<?php
// backend/api/drawings/probe.php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Drawing;
use Lib\DrawingNeighbors;
use Lib\Validation;

try {
    $deps = dependencies();
    $db   = $deps['db'];
    $req  = $deps['request']['input'] ?? [];

    // Accept both flat JSON and { input: {...} } (bootstrap unwraps already).
    $notebookId       = isset($req['notebook_id']) ? (int)$req['notebook_id'] : 0;
    $primarySectionId = isset($req['section_id'])  ? (int)$req['section_id']  : 0;
    $page             = isset($req['page'])        ? (int)$req['page']        : 0;
    $neighbors        = is_array($req['neighbors'] ?? null) ? $req['neighbors'] : [];
    $excludeId        = isset($req['exclude_drawing_id']) ? (int)$req['exclude_drawing_id'] : null;

    // Basic shape sanity (soft probe — we won't hard-fail unless totally unusable)
    if ($notebookId <= 0 || $primarySectionId <= 0) {
        ApiResponse::badRequest("notebook_id and section_id are required");
        exit;
    }

    // --- PRIMARY SLOT PROBE -------------------------------------------------
    // We do a very forgiving check:
    // - If page <= 0, we can't probe primary; return nulls.
    // - If slot is occupied by excludeId → report taken=false, taken_by_self=true
    // - If slot is occupied by someone else → taken=true (+ drawing_id)
    // - Else → taken=false
    $primary = [
        'taken'          => null,
        'taken_by_self'  => false,
    ];

    if ($page > 0) {
        // ask DB which drawing (if any) occupies this slot
        $row = $db->querySingle(
            "SELECT drawing_id 
               FROM drawings 
              WHERE notebook_id = ? AND section_id = ? AND page = ?
              LIMIT 1",
            [$notebookId, $primarySectionId, $page]
        );

        $occupiedId = isset($row['drawing_id']) ? (int)$row['drawing_id'] : null;

        if ($occupiedId !== null) {
            if ($excludeId !== null && $occupiedId === $excludeId) {
                // It's me. Treat as free for UI purposes, but signal it's self.
                $primary['taken'] = false;
                $primary['taken_by_self'] = true;
            } else {
                $primary['taken'] = true;
                $primary['taken_by_self'] = false;
                $primary['drawing_id'] = $occupiedId;
            }
        } else {
            $primary['taken'] = false;
            $primary['taken_by_self'] = false;
        }
    }

    // --- NEIGHBORS PROBE ----------------------------------------------------
    // Use non-throwing analysis. This will:
    // - cap count (section_count-1)
    // - warn if neighbor == primary section
    // - validate shapes against the notebook (collect warnings, not throws)
    // - warn if neighbor slot doesn't exist
    $neighborsAnalysis = DrawingNeighbors::analyze(
        $db,
        $notebookId,
        $primarySectionId,
        $neighbors
    );

    // Optional: light shape sanity on primary, but do NOT throw on probe.
    // (We keep it gentle to avoid blocking UI hints.)
    try {
        // Only try to validate page if present; ignore 0 to stay permissive in UI
        if ($page > 0) {
            Validation::notebook($notebookId, $db);
            Validation::section($primarySectionId, $notebookId, $db);
            Validation::page($page, $notebookId, $db);
        }
    } catch (\Throwable $e) {
        // Surface as a generic warning; but don't fail the whole probe.
        // We attach it under neighbors.warnings to reuse the same rendering path.
        $neighborsAnalysis['warnings'][] = [
            'code'    => 'primary_shape_warning',
            'message' => $e->getMessage(),
            'section_id' => $primarySectionId,
            'page'       => $page,
        ];
    }

    ApiResponse::success([
        'primary'   => $primary,           // { taken: bool|null, taken_by_self: bool, drawing_id?: int }
        'neighbors' => $neighborsAnalysis, // { valid, warnings[], accepted[] }
    ]);
} catch (\Throwable $e) {
    error_log('[drawings/probe] ' . $e->getMessage());
    ApiResponse::error('Probe failed', 400);
}
