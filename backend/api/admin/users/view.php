<?php
// backend/api/admin/users/view.php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;

try {
    $deps = dependencies();
    $db   = $deps['db'];

    // Admin gate
    Auth::init();
    $email = Auth::getEmail();
    if (!$email) ApiResponse::error('Not authenticated', 401);
    $row = $db->querySingle('SELECT is_admin FROM users WHERE email = ?', [$email]);
    if ((int)($row['is_admin'] ?? 0) !== 1) ApiResponse::error('Forbidden', 403);

    // Params
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        ApiResponse::error('Invalid user id', 400);
    }

    // Fetch
    $u = $db->querySingle(
        "SELECT user_id, email, is_admin, test, created_at, updated_at
         FROM users WHERE user_id = ?",
        [$id]
    );
    if (!$u) {
        ApiResponse::error('User not found', 404);
    }

    // Optional: include submission count
    $cnt = $db->querySingle(
        "SELECT COUNT(*) AS c FROM drawings WHERE user_id = ?",
        [$id]
    );
    $submissions = (int)($cnt['c'] ?? 0);

    ApiResponse::success([
        'user_id'     => (int)$u['user_id'],
        'email'       => (string)$u['email'],
        'is_admin'    => (int)$u['is_admin'] === 1,
        'test'        => (int)($u['test'] ?? 0) === 1,
        'created_at'  => (string)$u['created_at'],
        'updated_at'  => (string)$u['updated_at'],
        'submissions' => $submissions,
    ]);

} catch (\Throwable $e) {
    error_log('[admin/users/view] '.$e->getMessage());
    ApiResponse::error('Failed to load user', 500);
}
