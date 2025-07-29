<?php

// backend/api/drawings/create.php

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;
use Lib\Config;
use Lib\FileHandler;
use Lib\FileHandlerException;
use Lib\User;
use Lib\Validation;

$deps = dependencies();
$db = $deps['db'];
$request = $deps['request']; // Contains parsed input and files

try {
    // ==============================================
    // 1. Validate Required Fields
    // ==============================================
    $required = ['email', 'notebook_id', 'section_id', 'page'];
    foreach ($required as $field) {
        if (empty($request['input'][$field])) {
            ApiResponse::error("Missing required field: $field", 400);
        }
    }

    $email = $request['input']['email'];
    $notebookId = (int)$request['input']['notebook_id'];
    $sectionId = (int)$request['input']['section_id'];
    $page = (int)$request['input']['page'];

    // ==============================================
    // 2. Validate Email Format
    // ==============================================
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        ApiResponse::error("Invalid email format", 400);
    }

    // ==============================================
    // 3. Handle User Registration/Authentication
    // ==============================================
    try {
        $userId = User::getIdByEmail($db, $email);
        
        if (!$userId) {
            // Check if new registrations are allowed
            if (!Config::get('allow_submission_registry')) {
                ApiResponse::error("New account registration is currently disabled", 403);
            }

            // Validate auth method
            if (Config::get('auth_method') !== Auth::METHOD_EMAIL_ONLY) {
                ApiResponse::error("This authentication method is not supported", 400);
            }
            
            $userId = User::register($db, $email);
        }
    } catch (RuntimeException $e) {
        // Catches all User::register() exceptions with ready-to-use messages
        ApiResponse::error($e->getMessage(), 400);
    } catch (PDOException $e) {
        error_log("Database error during registration: " . $e->getMessage());
        ApiResponse::error("Our registration system is temporarily unavailable", 503);
    }

    // ==============================================
    // 4. Validate Drawing Parameters
    // ==============================================
    $notebookId = Validation::notebook($notebookId, $db);
    $sectionId = Validation::section($sectionId, $notebookId, $db);
    $page = Validation::page($page, $notebookId, $db);

    // ==============================================
    // 5. Check for Existing Drawing
    // ==============================================
    if ($db->querySingle(
        "SELECT 1 FROM drawings 
         WHERE notebook_id = ? AND section_id = ? AND page = ?",
        [$notebookId, $sectionId, $page]
    )) {
        ApiResponse::conflict("This drawing slot is already taken");
    }

    // ==============================================
    // 6. Create Drawing Record (Database Transaction)
    // ==============================================
    try {
        $db->beginTransaction();

        // Insert drawing record
        $db->execute(
            "INSERT INTO drawings (user_id, notebook_id, section_id, page)
            VALUES (?, ?, ?, ?)",
            [$userId, $notebookId, $sectionId, $page]
        );
        $drawingId = $db->lastInsertId();

        // Handle file upload (with separate error handling)
        $fileMeta = null;
        if (!empty($request['files']['drawing'])) {
            try {
                $fileMeta = FileHandler::processUpload($request['files']['drawing'], $drawingId);
                
                $db->execute(
                    "INSERT INTO files 
                    (drawing_id, stored_filename, original_filename, filesize, mime_type, width, height, test) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                    [
                        $fileMeta['drawing_id'],
                        $fileMeta['stored_filename'],
                        $fileMeta['original_filename'],
                        $fileMeta['filesize'],
                        $fileMeta['mime_type'],
                        $fileMeta['width'],
                        $fileMeta['height'],
                        (int)$fileMeta['test'] // Explicit cast to integer (0/1)
                    ]
                );
                            
            } catch (FileHandlerException $e) {
                $db->rollBack();
                
                error_log("File upload failed - " . $e->getMessage() . "\nContext: " . 
                        json_encode($e->getContext(), JSON_PRETTY_PRINT));
                
                return ApiResponse::error(
                    "Failed to process image upload",
                    400,
                    Env::get('ENV') === 'development' ? [
                        'debug' => $e->getMessage(),
                        'context' => $e->getContext()
                    ] : []
                );
            }
        }

        $db->commit();

        ApiResponse::success([
            'drawing_id' => $drawingId,
            'user_id' => $userId,
            'file_uploaded' => $fileMeta !== null,
            'preview_url' => $fileMeta ? '/uploads/' . basename($fileMeta['filepath']) : null
        ]);

    } catch (\PDOException $e) {
        $db->rollBack();
        error_log("Database error: " . $e->getMessage());
        ApiResponse::error("Database operation failed", 500);
    } catch (Exception $e) {
        $db->rollBack();
        error_log("System error: " . $e->getMessage());
        ApiResponse::error("Failed to save drawing", 500);
    }

} catch (InvalidArgumentException $e) {
    ApiResponse::validationError(['error' => $e->getMessage()]);
} catch (RuntimeException $e) {
    ApiResponse::error($e->getMessage(), 400);
}