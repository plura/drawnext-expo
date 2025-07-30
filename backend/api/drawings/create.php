<?php
// backend/api/drawings/create.php

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

use Lib\{
    ApiResponse,
    Auth,
    Config,
    Env,
    FileHandler,
    FileHandlerException,
    User,
    Validation
};

$deps = dependencies();
$db = $deps['db'];
$request = $deps['request'];

try {
    // 1. Validate Required Fields
    $requiredFields = ['email', 'notebook_id', 'section_id', 'page'];
    foreach ($requiredFields as $field) {
        if (empty($request['input'][$field])) {
            ApiResponse::error("Missing required field: $field", 400);
        }
    }

    // 2. Validate and Parse Inputs
    $email = filter_var($request['input']['email'], FILTER_VALIDATE_EMAIL)
        ?: ApiResponse::error("Invalid email format", 400);

    $notebookId = Validation::notebook((int)$request['input']['notebook_id'], $db);
    $sectionId = Validation::section((int)$request['input']['section_id'], $notebookId, $db);
    $page = Validation::page((int)$request['input']['page'], $notebookId, $db);

    // 3. Handle User Auth
    try {
        $userId = User::getIdByEmail($db, $email) 
            ?? match (true) {
                !Config::get('allow_submission_registry') => 
                    ApiResponse::error("New registrations disabled", 403),
                Config::get('auth_method') !== Auth::METHOD_EMAIL_ONLY => 
                    ApiResponse::error("Unsupported auth method", 400),
                default => User::register($db, $email)
            };
    } catch (PDOException $e) {
        error_log("[" . date('c') . "] DB Error: " . $e->getMessage());
        ApiResponse::error("Registration system unavailable", 503);
    }

    // 4. Check Slot Availability
    if ($db->querySingle(
        "SELECT 1 FROM drawings WHERE notebook_id = ? AND section_id = ? AND page = ?",
        [$notebookId, $sectionId, $page]
    )) {
        ApiResponse::conflict("Drawing slot already taken");
    }

    // 5. Create Drawing (Atomic Transaction)
    try {
        $db->beginTransaction();

        // Insert drawing record
        $db->execute(
            "INSERT INTO drawings (user_id, notebook_id, section_id, page) VALUES (?, ?, ?, ?)",
            [$userId, $notebookId, $sectionId, $page]
        );
        $drawingId = $db->lastInsertId();

        // Handle file upload
        $fileMeta = null;
        if (!empty($request['files']['drawing'])) {
            try {
                $fileMeta = FileHandler::processUpload(
                    $request['files']['drawing'],
                    $drawingId,
                    (bool)Env::get('TEST_MODE', false)
                );

                // Explicit positional parameters (clear field-value mapping)
                $db->execute(
                    "INSERT INTO files 
                    (drawing_id, stored_filename, original_filename, filesize, mime_type, width, height, test) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $fileMeta['drawing_id'],          // 1. drawing_id (int)
                        $fileMeta['stored_filename'],     // 2. stored_filename (string)
                        $fileMeta['original_filename'],   // 3. original_filename (string)
                        $fileMeta['filesize'],            // 4. filesize (int)
                        $fileMeta['mime_type'],           // 5. mime_type (string)
                        $fileMeta['width'],               // 6. width (int)
                        $fileMeta['height'],              // 7. height (int)
                        (int)$fileMeta['test']            // 8. test (0/1)
                    ]
                );
            } catch (FileHandlerException $e) {
                $db->rollBack();
                error_log("[" . date('c') . "] File Upload Error: " . $e->getMessage());
                ApiResponse::error(
                    "File processing failed",
                    400,
                    Env::get('ENV') === 'development' ? ['debug' => $e->getMessage()] : null
                );
            }
        }

        $db->commit();

        ApiResponse::success([
            'drawing_id' => $drawingId,
            'user_id' => $userId,
            'file_uploaded' => $fileMeta !== null,
            'preview_url' => $fileMeta 
                ? '/uploads/' . rawurlencode(basename($fileMeta['filepath']))
                : null
        ]);

    } catch (PDOException $e) {
        $db->rollBack();
        error_log("[" . date('c') . "] DB Error: " . $e->getMessage());
        ApiResponse::error("Database operation failed", 500);
    } catch (Throwable $e) {
        $db->rollBack();
        error_log("[" . date('c') . "] System Error: " . $e->getMessage());
        ApiResponse::error("Operation failed", 500);
    }

} catch (InvalidArgumentException $e) {
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    ApiResponse::error($e->getMessage(), 400);
}