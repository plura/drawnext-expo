<?php
// backend/lib/Config.php

namespace Lib;

use RuntimeException;

/**
 * Config
 * -------
 * - Loads all rows from the `config` table once.
 * - Ensures every key exposes an *effective*, already-typed value via get():
 *     - If options are defined, only allowed values are admitted.
 *       Otherwise falls back to `default`, or finally the first option.
 *     - If no options are defined, returns `value` or `default` as before.
 *
 * Value types (value_type):
 *   boolean | int | number | csv | string
 */
class Config
{
    protected static ?Config $instance = null;

    /**
     * Cache layout:
     *  [
     *    'key' => [
     *      'value'   => mixed   // effective, already typed
     *      'type'    => string  // 'boolean'|'int'|'number'|'csv'|'string'
     *      'allowed' => array   // normalized tokens derived from options CSV (can be [])
     *      // 'raw'  => ['value'=>?string,'default'=>?string,'options'=>?string]  // (debug only)
     *    ],
     *    ...
     *  ]
     */
    protected array $data = [];
    protected Database $db;

    protected function __construct(Database $db)
    {
        $this->db = $db;
        $this->load(); // Load all configs on init
    }

    public static function init(Database $db): void
    {
        if (self::$instance === null) {
            self::$instance = new self($db);
        }
    }

    /**
     * Return the typed, validated (effective) value.
     * Throws if the key is unknown.
     */
    public static function get(string $key): mixed
    {
        $instance = self::$instance;
        if (!$instance || !isset($instance->data[$key])) {
            throw new RuntimeException("Config key '$key' doesn't exist");
        }
        return $instance->data[$key]['value'];
    }

    /**
     * Update a key's value (validates against options if present),
     * writes to DB, and updates the in-memory effective value.
     */
    public static function set(string $key, mixed $value): bool
    {
        $instance = self::$instance;
        if (!$instance || !isset($instance->data[$key])) {
            throw new RuntimeException("Config key '$key' doesn't exist");
        }

        $meta    = $instance->data[$key];
        $type    = $meta['type'];
        $allowed = $meta['allowed']; // normalized tokens (may be [])

        // Validate against options (if any)
        if (!empty($allowed)) {
            $token = self::tokenForCompare(self::normalizeForWrite($value, $type), $type);
            if ($token === null || !in_array($token, $allowed, true)) {
                throw new RuntimeException("Value not allowed for '$key'");
            }
        }

        // Convert to DB-safe scalar string
        $dbValue = self::toDbScalar($value, $type);

        // Persist
        $instance->db->execute(
            "UPDATE config SET `value` = ? WHERE `key` = ?",
            [$dbValue, $key]
        );

        // Recompute effective typed value for cache
        $instance->data[$key]['value'] = self::parseValue($dbValue, $type);

        return true;
    }

    // -------- Internal: load & normalize ------------------------------------

    protected function load(): void
    {
        $rows = $this->db->query("SELECT `key`, `value`, `default`, `value_type`, `options` FROM config");
        foreach ($rows as $row) {
            $key        = (string)($row['key'] ?? '');
            $type       = (string)($row['value_type'] ?? 'string');
            $rawValue   = isset($row['value']) ? (string)$row['value'] : null;
            $rawDefault = isset($row['default']) ? (string)$row['default'] : null;
            $rawOptions = isset($row['options']) ? (string)$row['options'] : null;

            $allowed = self::normalizeOptions($rawOptions, $type); // tokens or []

            // Choose the raw effective value.
            // If options exist: prefer stored value if allowed; else default if allowed; else pick first option.
            // If no options: use stored value if set, else default.
            $chosenRaw = null;
            if (!empty($allowed)) {
                $vTok = self::tokenForCompare($rawValue, $type);
                $dTok = self::tokenForCompare($rawDefault, $type);

                if ($vTok !== null && in_array($vTok, $allowed, true)) {
                    $chosenRaw = $rawValue;
                } elseif ($dTok !== null && in_array($dTok, $allowed, true)) {
                    $chosenRaw = $rawDefault;
                } else {
                    // Fall back to first allowed option token
                    // (works well for string enums like 'strict,permissive,ignore')
                    $chosenRaw = $allowed[0];
                }
            } else {
                $chosenRaw = ($rawValue !== null && $rawValue !== '') ? $rawValue : $rawDefault;
            }

            $this->data[$key] = [
                'value'   => self::parseValue($chosenRaw, $type),
                'type'    => $type,
                'allowed' => $allowed,
                // 'raw'   => ['value' => $rawValue, 'default' => $rawDefault, 'options' => $rawOptions],
            ];
        }
    }

    // -------- Internal: typing & comparison helpers --------------------------

    /**
     * Parse a raw string (or null) to the configured PHP type.
     */
    private static function parseValue(?string $raw, string $type): mixed
    {
        switch ($type) {
            case 'boolean':
                if ($raw === null) return false;
                $t = strtolower(trim($raw));
                return in_array($t, ['1','true','yes','y','on'], true);

            case 'int':
                return (int)($raw ?? 0);

            case 'number':
                if ($raw === null || $raw === '') return 0;
                return is_numeric($raw) ? ($raw + 0) : 0; // int or float

            case 'csv':
                if ($raw === null || $raw === '') return [];
                $parts = array_map('trim', explode(',', $raw));
                return array_values(array_filter($parts, fn($s) => $s !== ''));

            case 'string':
            default:
                return $raw ?? '';
        }
    }

    /**
     * Normalize the CSV options into comparable tokens.
     * Currently optimized for 'string' enums (e.g., 'strict,permissive,ignore').
     */
    private static function normalizeOptions(?string $csv, string $type): array
    {
        if ($csv === null || trim($csv) === '') return [];
        $items = array_map('trim', explode(',', $csv));
        $items = array_values(array_filter($items, fn($s) => $s !== ''));
        if ($type === 'string') {
            return array_map('strtolower', $items);
        }
        // You can extend this if you ever define options for non-string types
        return $items;
    }

    /**
     * Produce a normalized token for comparing a raw value against allowed options.
     */
    private static function tokenForCompare(?string $raw, string $type): ?string
    {
        if ($raw === null) return null;
        $s = trim((string)$raw);
        if ($s === '') return null;
        if ($type === 'string') return strtolower($s);
        return $s;
    }

    /**
     * Normalize an incoming set() value to a string for token comparison.
     */
    private static function normalizeForWrite(mixed $value, string $type): string
    {
        switch ($type) {
            case 'boolean': return ((bool)$value) ? 'true' : 'false';
            case 'int':     return (string)(int)$value;
            case 'number':  return (string)(is_numeric($value) ? ($value + 0) : $value);
            case 'csv':     return is_array($value) ? implode(',', $value) : (string)$value;
            case 'string':
            default:        return (string)$value;
        }
    }

    /**
     * Convert a value to a scalar string suitable for DB storage in `config.value`.
     */
    private static function toDbScalar(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => ((bool)$value) ? '1' : '0',
            'int'     => (string)(int)$value,
            'number'  => (string)(is_numeric($value) ? ($value + 0) : 0),
            'csv'     => is_array($value) ? implode(',', $value) : (string)$value,
            default   => (string)$value, // 'string'
        };
    }
}
