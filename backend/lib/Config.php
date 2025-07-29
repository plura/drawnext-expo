<?php

// backend/lib/Config.php

namespace Lib;

use RuntimeException;

class Config {
    protected static ?Config $instance = null;
    protected array $data = []; // ['key' => ['value' => mixed, 'default' => mixed, 'type' => string, 'options' => ?array]]
    protected Database $db;

    protected function __construct(Database $db) {
        $this->db = $db;
        $this->load(); // Load all configs on init
    }

    protected function load(): void {
        $rows = $this->db->query("SELECT `key`, `value`, `default`, `value_type`, `options` FROM config");
        foreach ($rows as $row) {
            $this->data[$row['key']] = [
                'value'   => $this->parseValue($row['value'], $row['value_type']),
                'default' => $this->parseValue($row['default'], $row['value_type']), // Same parsing for defaults
                'type'    => $row['value_type'],
                'options' => $row['options'] ? explode(',', $row['options']) : null // Pre-split options
            ];
        }
    }

    protected function parseValue(string $value, string $type): mixed {
        return match($type) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'int'     => (int)$value,
            'number'  => is_numeric($value) ? $value + 0 : $value, // Auto-convert to int/float
            'csv'     => explode(',', $value),
            default   => $value // 'string' or fallback
        };
    }

    public static function init(Database $db): void {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
    }

    public static function get(string $key): mixed {
        $instance = self::$instance;
        
        if (!isset($instance->data[$key])) {
            throw new \RuntimeException("Config key '$key' doesn't exist");
        }

        return $instance->data[$key]['value'] ?? $instance->data[$key]['default'];
    }

    public static function set(string $key, mixed $value): bool {
        $instance = self::$instance;

        if (!isset($instance->data[$key])) {
            throw new RuntimeException("Config key '$key' doesn't exist");
        }

        $meta = $instance->data[$key];

        // Validate against allowed options (if defined)
        if ($meta['options']) {
            $valueStr = is_array($value) ? implode(',', $value) : (string)$value;
            if (!in_array($valueStr, $meta['options'], true)) {
                throw new RuntimeException("Value not allowed for '$key'");
            }
        }

        // Convert to DB-safe format
        $validated = match($meta['type']) {
            'boolean' => (bool)$value,
            'int'     => (int)$value,
            'number'  => is_numeric($value) ? $value + 0 : throw new RuntimeException("Invalid number"),
            'csv'     => is_array($value) ? implode(',', $value) : $value,
            default   => (string)$value
        };

        // Update DB and cache
        $instance->db->execute(
            "UPDATE config SET `value` = ? WHERE `key` = ?",
            [$validated, $key]
        );
        $instance->data[$key]['value'] = $instance->parseValue($validated, $meta['type']);
        return true;
    }

}