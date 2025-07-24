<?php
require_once __DIR__ . '/../../lib/Database.php';
require_once __DIR__ . '/../../lib/Validation.php';
require_once __DIR__ . '/../../lib/ApiResponse.php';

header('Content-Type: application/json');

try {
    // 1. Get and validate input
    $input = json_decode(file_get_contents('php://input'), true);
    $db = new Database();
    
    $userId = Validation::email($input['email'] ?? '', $db);
    $notebookId = Validation::notebook($input['notebook_id'] ?? 0, $db);
    $sectionId = Validation::section($input['section_id'] ?? 0, $input['notebook_id'] ?? 0, $db);
    $page = Validation::page($input['page'] ?? 0, $input['notebook_id'] ?? 0, $db);

    // 2. Check for existing drawing
    $existing = $db->querySingle(
        "SELECT 1 FROM drawings 
         WHERE notebook_id = ? AND section_id = ? AND page = ?",
        [$notebookId, $sectionId, $page]
    );
    
    if ($existing) {
        ApiResponse::conflict("This section/page is already filled");
    }

    // 3. Insert drawing
    $db->execute(
        "INSERT INTO drawings 
         (user_id, notebook_id, section_id, page, timestamp) 
         VALUES (?, ?, ?, ?, NOW())",
        [$userId, $notebookId, $sectionId, $page]
    );

    // 4. Success response
    ApiResponse::success([
        'drawing_id' => $db->lastInsertId(),
        'section_id' => $sectionId,
        'page' => $page
    ]);

} catch (InvalidArgumentException $e) {
    ApiResponse::validationError([$e->getMessage()]);
} catch (PDOException $e) {
    ApiResponse::error("Database error: " . $e->getMessage(), 500);
}