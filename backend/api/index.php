<?php

//backend/api/index.php (API entry point)

declare(strict_types=1);

// ---- Minimal router for /api/* ----
// NOTE: place this file at backend/api/index.php

// 1) Parse path and strip leading "api/"
$uri  = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = ltrim($uri, '/');                // "api/notebooks/config"
$path = preg_replace('#^api/#i', '', $path); // "notebooks/config"
$path = rtrim($path, '/');               // "notebooks/config" (no trailing slash)

// 2) Split into up to two segments we route on
$parts = explode('/', $path, 3);
$seg1  = $parts[0] ?? '';
$seg2  = $parts[1] ?? '';
$key   = ($seg1 && $seg2) ? ($seg1 . '/' . $seg2) : ($seg1 ?: '');

// 3) Static whitelist (keeps things explicit & safe)
$routes = [
  'notebooks/config' => __DIR__ . '/notebooks/config.php',
  'drawings/create'  => __DIR__ . '/drawings/create.php',
  'drawings/list'    => __DIR__ . '/drawings/list.php',
  'drawings/validate'=> __DIR__ . '/drawings/validate.php',

  'auth/login'       => __DIR__ . '/auth/login.php',
	'auth/logout'      => __DIR__ . '/auth/logout.php',
	'admin/me'         => __DIR__ . '/admin/me.php',
  'admin/ping'       => __DIR__ . '/admin/ping.php',
  // 'drawings/view'  => __DIR__ . '/drawings/view.php', // (add when ready)
];

// 4) Special cases for nicer REST-ish URLs
// - /api/drawings           -> list
// - /api/drawings/123       -> view?id=123 (future)
// These run before the static whitelist.
if ($seg1 === 'drawings') {
  // /api/drawings  (no second segment): list
  if ($seg2 === '' || $seg2 === null) {
    require __DIR__ . '/drawings/list.php';
    exit;
  }

  // /api/drawings/{id} (numeric): view?id={id}
  if (ctype_digit($seg2)) {
    // Only dispatch if view.php exists (keeps router robust if not added yet)
    $viewPath = __DIR__ . '/drawings/view.php';
    if (is_file($viewPath)) {
      $_GET['id'] = (int)$seg2;
      require $viewPath;
      exit;
    }
  }
}

// 5) Normal dispatch via whitelist
if ($key && isset($routes[$key]) && is_file($routes[$key])) {
  require $routes[$key];
  exit;
}

// 6) 404 JSON response
http_response_code(404);
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
  'status'  => 'error',
  'message' => 'API endpoint not found',
]);
