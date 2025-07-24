<?php
// backend/lib/Auth.php

class Auth {
	public static function init() {
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
	}

	// Unified login method (adapts to EMAIL_ONLY or PASSWORD mode)
	public static function login(string $email, ?string $password = null): bool {
		self::init();

		$authMode = Config::get('auth_mode', 'EMAIL_ONLY');

		if ($authMode === 'EMAIL_ONLY') {
			// Email-only mode: Trust the email immediately
			$_SESSION['user_email'] = $email;
			return true;
		} elseif ($authMode === 'PASSWORD') {
			// Password mode
			if ($password && self::validatePassword($email, $password)) {
				$_SESSION['user_email'] = $email;
				return true;
			}
			return false;
		}

		// Unknown mode fallback
		return false;
	}

	public static function getEmail(): ?string {
		self::init();
		return $_SESSION['user_email'] ?? null;
	}

	private static function validatePassword(string $email, string $password): bool {
		// TODO: Implement proper validation
		return false;
	}
}
