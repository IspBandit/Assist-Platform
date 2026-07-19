<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Minimal .env loader. Parses a KEY=VALUE file once and exposes typed access.
 * No external dependency so it runs on plain shared cPanel hosting.
 */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip surrounding quotes if present.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            self::$vars[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        // Real process variables take precedence so containers, CI, and
        // one-off validation commands can safely override a local .env file.
        $env = getenv($key);
        if ($env !== false) {
            return self::cast($env);
        }
        return array_key_exists($key, self::$vars)
            ? self::cast(self::$vars[$key])
            : $default;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::$vars) || getenv($key) !== false;
    }

    private static function cast(string $value): mixed
    {
        return match (strtolower($value)) {
            'true', '(true)'   => true,
            'false', '(false)' => false,
            'null', '(null)'   => null,
            'empty', '(empty)' => '',
            default            => $value,
        };
    }
}
