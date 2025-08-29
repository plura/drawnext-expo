<?php
//api/auth/login.php
declare(strict_types=1);

/**
 * Auth: Email-only login
 * ----------------------------------------------
 * Purpose:
 * 	- Establishes a session for an existing user by email (no password).
 * 	- Returns whether the user is an admin (is_admin).
 *
 * Method: POST
 * Body (JSON):
 * 	{ "email": "user@example.com" }
 *
 * Responses:
 * 	200 success: { status:"success", data:{ email:string, is_admin:boolean } }
 * 	400/404/405 on error with message
 *
 * Notes:
 * 	- Does NOT auto-register; only existing users can log in.
 * 	- Uses server-side session (see bootstrap.php).
 */

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Validation;
use Lib\Database;
use Lib\User;
use Lib\Auth;

$deps = dependencies();
/** @var Database $db */
$db = $deps['db'];

try {
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		ApiResponse::error('Method not allowed', 405);
	}

	$body  = $deps['request']['input'] ?? [];
	$email = isset($body['email']) ? Validation::email((string)$body['email']) : null;
	if (!$email) {
		ApiResponse::error('Email required', 400);
	}

	// Only allow existing users (prevents random session creation)
	$userId = User::getIdByEmail($db, $email);
	if (!$userId) {
		ApiResponse::error('No account for this email', 404);
	}

	// Establish session
	if (!Auth::login($email)) {
		ApiResponse::error('Login failed', 400);
	}

	$row = $db->querySingle('SELECT is_admin FROM users WHERE user_id = ?', [$userId]);

	ApiResponse::success([
		'email'    => $email,
		'is_admin' => (int)($row['is_admin'] ?? 0) === 1,
	]);
} catch (\Throwable $e) {
	error_log('[auth/login] ' . $e->getMessage());
	ApiResponse::error('Login error', 400);
}
