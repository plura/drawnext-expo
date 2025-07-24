<?php

// backend/lib/Database.php

/**
 * Database handler with PDO wrapper
 * - Auto-loads credentials from .env
 * - Falls back to DDEV defaults if .env missing
 */
class Database {
    private PDO $pdo;
    
    public function __construct() {
        // Load credentials with DDEV fallbacks
        $host = $_ENV['DB_HOST'] ?? 'db';
        $name = $_ENV['DB_NAME'] ?? 'db';
        $user = $_ENV['DB_USER'] ?? 'db';
        $pass = $_ENV['DB_PASS'] ?? 'db';

        try {
            $this->pdo = new PDO(
                "mysql:host=$host;dbname=$name;charset=utf8mb4",
                $user,
                $pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Execute SELECT query and return all rows
     */
    public function query(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Execute SELECT and return first row
     */
    public function querySingle(string $sql, array $params = []): ?array {
        return $this->query($sql, $params)[0] ?? null;
    }

    /**
     * Execute INSERT/UPDATE/DELETE
     */
    public function execute(string $sql, array $params = []): int {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * Get last inserted ID
     */
    public function lastInsertId(): int {
        return (int)$this->pdo->lastInsertId();
    }
}