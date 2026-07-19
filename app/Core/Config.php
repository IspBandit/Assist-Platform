<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Loads PHP config files from the /config directory and provides
 * dot-notation access, e.g. Config::get('app.name').
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $items = [];
    private static bool $loaded = false;

    public static function load(string $configDir): void
    {
        if (self::$loaded) {
            return;
        }
        foreach (glob(rtrim($configDir, '/\\') . '/*.php') ?: [] as $file) {
            $name = basename($file, '.php');
            self::$items[$name] = require $file;
        }
        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $segments = explode('.', $key);
        $value = self::$items;
        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }
        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $ref = &self::$items;
        foreach ($segments as $i => $segment) {
            if ($i === count($segments) - 1) {
                $ref[$segment] = $value;
            } else {
                if (!isset($ref[$segment]) || !is_array($ref[$segment])) {
                    $ref[$segment] = [];
                }
                $ref = &$ref[$segment];
            }
        }
    }

    public static function all(): array
    {
        return self::$items;
    }
}
