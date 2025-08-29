<?php
//api/admin/me.php
declare(strict_types=1);

/**
 * Admin: Current session info
 * ----------------------------------------------
 * Purpose:
 * 	- Returns the logged-in user's email and admin flag.
 * 	- Used by the frontend "AdminGate" to allow/deny access.
 *
 * Method: GET
 *
 * Responses:
 * 	200 success: { status:"success", data:{ email:string, is_admin:boolean } }
 * 	401 if not authenticated
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

// Look up is_admin by email (avoids requiring extra helpers for now)
$row = $db->querySingle('SELECT is_admin FROM users WHERE email = ?', [$email]);
$isAdmin = (int)($row['is_admin'] ?? 0) === 1;

ApiResponse::success([
	'email'    => $email,
	'is_admin' => $isAdmin,
]);
