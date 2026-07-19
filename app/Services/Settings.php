<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Reads and writes editable site settings stored in the site_settings table.
 * Values are cached for the duration of the request.
 */
final class Settings
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $key, mixed $default = null): mixed
    {
        self::loadCache();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, string $value): void
    {
        Database::query(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
            [$key, $value]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $value;
        }
    }

    public static function isMaintenanceMode(): bool
    {
        return self::get('maintenance_mode', '0') === '1';
    }

    public static function launchMode(): string
    {
        return (string) self::get('launch_mode', (string) config('app.launch_mode', 'private'));
    }

    public static function all(): array
    {
        self::loadCache();
        return self::$cache ?? [];
    }

    private static function loadCache(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            $rows = Database::select('SELECT setting_key, setting_value FROM site_settings');
            foreach ($rows as $row) {
                self::$cache[(string) $row['setting_key']] = (string) $row['setting_value'];
            }
        } catch (Throwable) {
            // Table may not exist yet (pre-install). Leave cache empty.
            self::$cache = [];
        }
    }
}
