<?php
//api/admin/ping.php
declare(strict_types=1);

/**
 * Admin: Ping (health/access check)
 * ----------------------------------------------
 * Purpose:
 * 	- Fast way to verify the session is admin-capable.
 * 	- Useful for admin-only pages to quickly reject unauthorised users.
 *
 * Method: GET
 *
 * Responses:
 * 	200 success: { status:"success", data:{ ok:true } }
 * 	401 if not authenticated
 * 	403 if not admin
 */

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;

$deps = dependencies();
$db   = $deps['db'];

Auth::init();
$email = Auth::getEmail();
if (!$email) {
	ApiResponse::error('Not authenticated', 401);
}

$row = $db->querySingle('SELECT is_admin FROM users WHERE email = ?', [$email]);
if ((int)($row['is_admin'] ?? 0) !== 1) {
	ApiResponse::error('Forbidden', 403);
}

ApiResponse::success(['ok' => true]);
