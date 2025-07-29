<?php
// backend/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

use Lib\Env;
use Lib\Database;
use Lib\Config;

// 2. Initialize environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->safeLoad();

// 3. Set error handling
ini_set('log_errors', '1');
if (Env::get('ENV', 'production') === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED);
    ini_set('display_errors', '0');
}

// 4. Configure timezone
date_default_timezone_set(Env::get('TIMEZONE', 'UTC'));

/**
 * Raw request parser
 */
function parseRequest(): array {
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    return match(true) {
        str_starts_with($contentType, 'multipart/form-data') => [
            'input' => $_POST,
            'files' => $_FILES ?? []
        ],
        $contentType === 'application/json' => [
            'input' => json_decode(file_get_contents('php://input'), true) ?? [],
            'files' => []
        ],
        default => [
            'input' => parse_str(file_get_contents('php://input'), $input) ? $input : [],
            'files' => []
        ]
    };
}

/**
 * Dependency container
 */
function dependencies(): array {
    static $deps = null;
    
    if ($deps === null) {
        $deps = [
            'db' => new Database(),  // Now safely uses Env::get()
            'request' => parseRequest()
        ];
        
        Config::init($deps['db']);  // Initialize after DB is ready
        
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'secure' => Env::get('ENV') === 'production',
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        }
    }
    
    return $deps;
}