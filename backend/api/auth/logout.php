<?php
//api/auth/logout.php
declare(strict_types=1);

/**
 * Auth: Logout (destroy session)
 * ----------------------------------------------
 * Purpose:
 * 	- Clears current PHP session and cookie.
 *
 * Method: POST (recommended)
 *
 * Responses:
 * 	200 success: { status:"success", data:{ ok:true } }
 * 	400 on error
 */

require_once __DIR__ . '/../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;

try {
	if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
		ApiResponse::error('Method not allowed', 405);
	}

	Auth::init();
	$_SESSION = [];

	if (ini_get('session.use_cookies')) {
		$params = session_get_cookie_params();
		setcookie(
			session_name(),
			'',
			time() - 42000,
			$params['path'],
			$params['domain'],
			(bool)$params['secure'],
			(bool)$params['httponly']
		);
	}

	session_destroy();

	ApiResponse::success(['ok' => true]);
} catch (\Throwable $e) {
	error_log('[auth/logout] ' . $e->getMessage());
	ApiResponse::error('Logout error', 400);
}
