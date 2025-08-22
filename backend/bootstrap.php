<?php
// backend/bootstrap.php

require_once __DIR__ . '/../vendor/autoload.php';

use Lib\Env;
use Lib\Database;
use Lib\Config;

// 1) Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->safeLoad();

// 2) Define project root (env first, fallback to backend/..)
if (!defined('PROJECT_ROOT')) {
	define('PROJECT_ROOT', Env::get('PROJECT_ROOT', realpath(__DIR__ . '/..')));
}

// 3) Error handling
ini_set('log_errors', '1');
if (Env::get('ENV', 'production') === 'development') {
	error_reporting(E_ALL);
	ini_set('display_errors', '1');
} else {
	error_reporting(E_ALL & ~E_DEPRECATED);
	ini_set('display_errors', '0');
}

// 4) Timezone
date_default_timezone_set(Env::get('TIMEZONE', 'UTC'));

/**
 * Parse incoming request into a normalized shape.
 * - For multipart: decode JSON in $_POST['input'] (if present) into array.
 */

function parseRequest(): array
{
    // Some SAPIs set CONTENT_TYPE, others HTTP_CONTENT_TYPE
    $ct = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $ct = strtolower(trim((string)$ct));

    // Read body ONCE (php://input is a stream)
    $raw = file_get_contents('php://input') ?: '';

    // Helper: try to decode a JSON string safely
    $tryDecodeJson = static function ($maybeJson) {
        if (is_string($maybeJson) && $maybeJson !== '') {
            $decoded = json_decode($maybeJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return null;
    };

    // Detect shapes
    $isMultipart = str_starts_with($ct, 'multipart/form-data');
    $isJsonCT    = str_starts_with($ct, 'application/json');
    $looksJson   = ($raw !== '') && preg_match('/^\s*[\{\[]/u', $raw) === 1;

    // Multipart/form-data: allow either flat fields, or a JSON blob in 'input'
    if ($isMultipart) {
        $data = $_POST ?? [];
        if (isset($data['input'])) {
            $decoded = $tryDecodeJson($data['input']);
            if ($decoded !== null) {
                $data = $decoded; // unwrap legacy {input:"...json..."}
            }
        }
        return [
            'input' => is_array($data) ? $data : [],
            'files' => $_FILES ?? [],
        ];
    }

    // JSON: accept flat, or unwrap if client sent { input: {...} }
    // Use either header OR body sniff (first non-space char is { or [)
    if ($isJsonCT || $looksJson) {
        $json = $tryDecodeJson($raw) ?? [];
        $data = (isset($json['input']) && is_array($json['input'])) ? $json['input'] : $json;

        return [
            'input' => is_array($data) ? $data : [],
            'files' => [],
        ];
    }

    // URL-encoded or unknown: best-effort parse of the raw body
    $parsed = [];
    parse_str($raw, $parsed);

    return [
        'input' => is_array($parsed) ? $parsed : [],
        'files' => [],
    ];
}




/**
 * Lightweight DI container
 */
function dependencies(): array {
	static $deps = null;

	if ($deps === null) {
		$deps = [
			'db' => new Database(),
			'request' => parseRequest()
		];

		Config::init($deps['db']);

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
