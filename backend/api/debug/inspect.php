<?php
//backend/api/debug/inspect.php
require_once __DIR__ . '/../../bootstrap.php';

// Raw body
$raw = file_get_contents('php://input') ?: '';

// Headers + server vars
$ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '(none)';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'content_type' => $ct,
    'method'       => $_SERVER['REQUEST_METHOD'] ?? null,
    'raw_length'   => strlen($raw),
    'raw_preview'  => substr($raw, 0, 200),
    'parsed'       => parseRequest(),  // <-- run your existing function
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
