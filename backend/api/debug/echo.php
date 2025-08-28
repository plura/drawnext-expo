<?php
//backend/api/debug/echo.php
declare(strict_types=1);
require_once __DIR__ . '/../../bootstrap.php';
$deps = dependencies();
header('Content-Type: application/json; charset=utf-8');
echo json_encode($deps['request'], JSON_PRETTY_PRINT);
