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
        if ($this->inTransaction()) {
            throw new \RuntimeException('Transaction already active');
        }
        $this->pdo->beginTransaction();
    }

    public function commit(): void 
    {
        if (!$this->inTransaction()) {
            throw new \RuntimeException('No active transaction to commit');
        }
        $this->pdo->commit();
    }

    public function rollBack(): void 
    {
        if ($this->inTransaction()) {
            $this->pdo->rollBack();
        }
        // Silent no-op if no transaction (defensive programming)
    }

    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
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