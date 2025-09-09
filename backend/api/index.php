<?php
// backend/api/index.php (API entry point)

declare(strict_types=1);

// ---- Minimal router for /api/* ----
// NOTE: place this file at backend/api/index.php

// 1) Derive original path robustly
$raw = $_SERVER['REDIRECT_URL']
    ?? $_SERVER['PATH_INFO']
    ?? $_SERVER['REQUEST_URI']
    ?? '/';

// Normalize to just the part after "/api/"
if (($pos = stripos($raw, '/api/')) !== false) {
    $path = substr($raw, $pos + 5); // after "/api/"
} else {
    $path = ltrim(parse_url($raw, PHP_URL_PATH) ?: '/', '/');
}

// Clean up
$path  = rtrim($path, '/');
$parts = explode('/', $path);
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';
$seg3  = $parts[2] ?? '';

// 3) Static whitelist (explicit & safe)
$routes = [
    'notebooks/config'       => __DIR__ . '/notebooks/config.php',

    'drawings/create'        => __DIR__ . '/drawings/create.php',
    'drawings/list'          => __DIR__ . '/drawings/list.php',
    'drawings/probe'         => __DIR__ . '/drawings/probe.php',
    'drawings/validate'      => __DIR__ . '/drawings/validate.php',

    'images/temp'            => __DIR__ . '/images/temp.php',

    'auth/login'             => __DIR__ . '/auth/login.php',
    'auth/logout'            => __DIR__ . '/auth/logout.php',

    'admin/me'               => __DIR__ . '/admin/me.php',
    'admin/ping'             => __DIR__ . '/admin/ping.php',

    'admin/drawings/delete'  => __DIR__ . '/admin/drawings/delete.php',
    'admin/drawings/update'  => __DIR__ . '/admin/drawings/update.php',
    'admin/drawings/view'    => __DIR__ . '/admin/drawings/view.php',
	'drawings/related'       => __DIR__ . '/drawings/related.php',

    'admin/users/list'       => __DIR__ . '/admin/users/list.php',
    'admin/users/view'       => __DIR__ . '/admin/users/view.php',
    // 'drawings/view'        => __DIR__ . '/drawings/view.php', // (add when ready)
];

// 4) Special cases for REST-ish URLs
if ($seg1 === 'drawings') {
    // /api/drawings -> list
    if ($seg2 === '' || $seg2 === null) {
        require __DIR__ . '/drawings/list.php';
        exit;
    }
    // /api/drawings/{id} (numeric) -> view?id={id}
    if (ctype_digit($seg2)) {
        $viewPath = __DIR__ . '/drawings/view.php';
        if (is_file($viewPath)) {
            $_GET['id'] = (int)$seg2;
            require $viewPath;
            exit;
        }
    }
}

// 5) Try whitelist (longest → shortest key)
foreach ([
    "$seg1/$seg2/$seg3",
    "$seg1/$seg2",
    "$seg1",
] as $try) {
    if ($try && isset($routes[$try]) && is_file($routes[$try])) {
        require $routes[$try];
        exit;
    }
}

// 6) 404 JSON response
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status'  => 'error',
    'message' => 'API endpoint not found',
]);
