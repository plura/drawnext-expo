<?php
// backend/lib/User.php

namespace Lib;

use RuntimeException;
use PDOException;
use Lib\Database;

class User {
    /**
     * Registers a user with email and optional password
     * @throws RuntimeException With user-friendly message ready for display
     */
    public static function register(Database $db, string $email, ?string $password = null): int {
        try {
            // 1. Check existing user
            if (self::getIdByEmail($db, $email)) {
                throw new RuntimeException('This email address is already registered. Please log in or use a different email.');
            }

            // 2. Handle password hashing
            $passwordHash = $password ? self::hashPassword($password, $email) : null;

            // 3. Insert user with clear error context
            $result = $db->execute(
                "INSERT INTO users (email, password_hash, created_at) 
                 VALUES (?, ?, NOW())",
                [$email, $passwordHash]
            );

            if ($result === false) {
                throw new RuntimeException('We couldn\'t create your account. Please try again.');
            }

            // 4. Verify creation
            $userId = $db->lastInsertId();
            if ($userId <= 0) {
                throw new RuntimeException('We encountered an issue verifying your account. Please contact support.');
            }

            return $userId;

        } catch (PDOException $e) {
            error_log("Registration PDOException: " . $e->getMessage());
            
            // Convert DB errors to user-friendly messages
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                throw new RuntimeException('That email address is already in use.', 0, $e);
            }
            if (str_contains($e->getMessage(), 'SQLSTATE[HY000]')) {
                throw new RuntimeException('Our systems are temporarily unavailable. Please try again soon.', 0, $e);
            }
            throw new RuntimeException('Something went wrong during registration. Our team has been notified.', 0, $e);
        }
    }

    /**
     * Finds user ID by email
     * @return int|null Returns user ID or null if not found
     */
    public static function getIdByEmail(Database $db, string $email): ?int {
        $user = $db->querySingle("SELECT user_id FROM users WHERE email = ?", [$email]);
        return $user ? (int)$user['user_id'] : null;
    }

    /**
     * Securely hashes passwords
     * @throws RuntimeException If hashing fails (with user-ready message)
     */
    private static function hashPassword(string $password, string $email): string {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if ($hash === false) {
            error_log("Password hashing failed for: " . substr($email, 0, 5) . "...");
            throw new RuntimeException('We couldn\'t secure your password. Please try a different one.');
        }
        return $hash;
    }
}