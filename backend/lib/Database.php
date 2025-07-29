<?php
// backend/lib/Database.php

namespace Lib;

use PDO;
use PDOException;
use RuntimeException;

class Database 
{
    private PDO $pdo;

    public function __construct() 
    {
        $host = Env::get('DB_HOST', 'db');
        $name = Env::get('DB_NAME', 'db');
        $user = Env::get('DB_USER', 'db');
        $pass = Env::get('DB_PASS', 'db');

        try {
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$name;charset=utf8mb4",
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false, // 🔥 Important for security
                ]
			);
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /* ---------------------- Transaction Methods ---------------------- */
    
    public function beginTransaction(): void 
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void 
    {
        $this->pdo->commit();
    }

    public function rollBack(): void 
    {
        $this->pdo->rollBack();
    }

    /* ---------------------- Core Methods ---------------------- */

    public function query(string $sql, array $params = []): array 
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function querySingle(string $sql, array $params = []): ?array 
    {
        return $this->query($sql, $params)[0] ?? null;
    }

    public function execute(string $sql, array $params = []): int 
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function lastInsertId(): int 
    {
        return (int) $this->pdo->lastInsertId();
    }
}