<?php
// backend/api/admin/users/update.php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

use Lib\ApiResponse;
use Lib\Auth;
use Lib\Validation;

try {
    $deps = dependencies();
    $db   = $deps['db'];
    $req  = $deps['request']['input'] ?? [];

    // ---- Admin gate ----
    Auth::init();
    $sessionEmail = Auth::getEmail();
    if (!$sessionEmail) ApiResponse::error('Not authenticated', 401);
    $row = $db->querySingle('SELECT is_admin FROM users WHERE email = ?', [$sessionEmail]);
    if ((int)($row['is_admin'] ?? 0) !== 1) ApiResponse::error('Forbidden', 403);

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        ApiResponse::error('Method not allowed', 405);
    }

    $userId = isset($req['user_id']) ? (int)$req['user_id'] : 0;
    if ($userId <= 0) {
        ApiResponse::validationError(['user_id' => 'Missing or invalid user_id']);
    }

    // Load current so we can (a) check self, (b) return merged row
    $current = $db->querySingle('SELECT user_id, email, is_admin, test, first_name, last_name, created_at, updated_at FROM users WHERE user_id = ?', [$userId]);
    if (!$current) ApiResponse::error('User not found', 404);

    $sets   = [];
    $params = [];

    // Optional fields — only update those present in payload
    if (array_key_exists('email', $req)) {
        $email = Validation::email((string)$req['email']);
        $sets[]   = 'email = ?';
        $params[] = $email;
    }
    if (array_key_exists('first_name', $req)) {
        $first = trim((string)$req['first_name']);
        $sets[]   = 'first_name = ?';
        $params[] = ($first === '') ? null : $first; // allow null
    }
    if (array_key_exists('last_name', $req)) {
        $last = trim((string)$req['last_name']);
        $sets[]   = 'last_name = ?';
        $params[] = ($last === '') ? null : $last; // allow null
    }
    if (array_key_exists('is_admin', $req)) {
        $isAdmin = (int)!!$req['is_admin'];

        // Safety: prevent self-demotion to avoid locking yourself out mid-session.
        if ($isAdmin === 0 && strcasecmp($current['email'], $sessionEmail) === 0) {
            ApiResponse::error('You cannot remove your own admin rights.', 400);
        }

        $sets[]   = 'is_admin = ?';
        $params[] = $isAdmin;
    }
    if (array_key_exists('test', $req)) {
        $sets[]   = 'test = ?';
        $params[] = (int)!!$req['test'];
    }

    if (!$sets) {
        ApiResponse::success(['updated' => false]); // nothing to do
    }

    $sql = 'UPDATE users SET ' . implode(', ', $sets) . ' WHERE user_id = ?';
    $params[] = $userId;

    try {
        $db->execute($sql, $params);
    } catch (\PDOException $e) {
        // Duplicate email, etc.
        if ((int)$e->getCode() === 23000 /* integrity */) {
            ApiResponse::validationError(['email' => 'Email already in use']);
        }
        throw $e;
    }

    // Return fresh row (same shape as /admin/users/view)
    $u = $db->querySingle(
        "SELECT user_id, email, first_name, last_name, is_admin, test, created_at, updated_at
         FROM users WHERE user_id = ?",
        [$userId]
    );

    ApiResponse::success([
        'updated'     => true,
        'user_id'     => (int)$u['user_id'],
        'email'       => (string)$u['email'],
        'first_name'  => $u['first_name'] !== null ? (string)$u['first_name'] : null,
        'last_name'   => $u['last_name']  !== null ? (string)$u['last_name']  : null,
        'is_admin'    => (int)$u['is_admin'] === 1,
        'test'        => (int)($u['test'] ?? 0) === 1,
        'created_at'  => (string)$u['created_at'],
        'updated_at'  => (string)$u['updated_at'],
    ]);

} catch (\Throwable $e) {
    error_log('[admin/users/update] ' . $e->getMessage());
    ApiResponse::error('Update failed', 400);
}
