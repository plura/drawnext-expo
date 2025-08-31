<?php
// backend/api/admin/users/list.php
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

    // Query params
    $limit  = isset($_GET['limit'])  ? max(1, min((int)$_GET['limit'], 100)) : 24;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;
    $q      = isset($_GET['q']) ? trim((string)$_GET['q']) : '';

    $where  = '';
    $params = [];

    if ($q !== '') {
        $where = 'WHERE u.email LIKE ?';
        $params[] = '%'.$q.'%';
    }

    // Total (for has_more, optional)
    $countSql = "SELECT COUNT(*) AS c FROM users u {$where}";
    $total = (int)($db->querySingle($countSql, $params)['c'] ?? 0);

    // Page
    $sql = "SELECT
                u.user_id,
                u.email,
                u.is_admin,
                u.test,
                u.created_at,
                u.modified_at
            FROM users u
            {$where}
            ORDER BY u.created_at DESC, u.user_id DESC
            LIMIT {$limit} OFFSET {$offset}";
    $rows = $db->query($sql, $params);

    $data = array_map(static function ($r) {
        return [
            'user_id'    => (int)$r['user_id'],
            'email'      => (string)$r['email'],
            'is_admin'   => (int)$r['is_admin'] === 1,
            'test'       => (int)($r['test'] ?? 0) === 1,
            'created_at' => (string)$r['created_at'],
            'modified_at'=> $r['modified_at'] ?? null,
        ];
    }, $rows);

    ApiResponse::success($data, [
        'limit'       => $limit,
        'offset'      => $offset,
        'count'       => count($data),
        'total'       => $total,
        'next_offset' => $offset + $limit,
        'has_more'    => ($offset + $limit) < $total,
    ]);

} catch (\Throwable $e) {
    error_log('[admin/users/list] '.$e->getMessage());
    ApiResponse::error('Failed to load users', 500);
}
