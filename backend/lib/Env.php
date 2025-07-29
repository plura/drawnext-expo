<?php
// backend/lib/Env.php

namespace Lib;

use RuntimeException;

class Env 
{
    /**
     * Gets an environment variable with type conversion
     * 
     * @throws RuntimeException If required variable is missing
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $default;
        
        if ($value === null) {
            throw new RuntimeException("Required ENV variable missing: $key");
        }

        return self::convertType($value);
    }

    private static function convertType(mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '1', 'on', 'yes' => true,
            'false', '0', 'off', 'no', 'none', '' => false,
            default => is_numeric($value) ? $value + 0 : $value
        };
    }
}