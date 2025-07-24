<?php
// backend/lib/ApiResponse.php
class ApiResponse {
    /**
     * Successful response (status 200)
     * @param mixed $data Main response payload
     * @param array $metadata Optional pagination/timestamps
     */
    public static function success($data = null, array $metadata = []) {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode([
            'status' => 'success',
            'data' => $data,
            'meta' => $metadata
        ]);
        exit;
    }

    /**
     * Error response with details
     * @param string $message Human-readable error
     * @param int $code HTTP status code (default: 400)
     * @param array $details Structured error info (e.g., validation)
     */
    public static function error(string $message, int $code = 400, array $details = []) {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode([
            'status' => 'error',
            'message' => $message,
            'details' => $details
        ]);
        exit;
    }

    /**
     * 404 Not Found (prebuilt for consistency)
     * @param string $resource Resource name (e.g., "Notebook")
     */
    public static function notFound(string $resource = 'Resource') {
        self::error("{$resource} not found", 404);
    }

    /**
     * 422 Validation Error (standard format)
     * @param array $errors Field => Error pairs
     */
    public static function validationError(array $errors) {
        self::error("Validation failed", 422, [
            'errors' => $errors
        ]);
    }

    /**
     * 409 Conflict - For your drawing uniqueness rule
     * @param string $description Specific conflict (e.g., "Section already filled")
     */
    public static function conflict(string $description) {
        self::error($description, 409);
    }
}