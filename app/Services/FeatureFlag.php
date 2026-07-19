<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * Reads and writes the database-backed feature_flags table (request-cached).
 * The master billing switch remains controlled by ENABLE_BILLING in .env;
 * these flags govern in-app future features as they are wired up.
 */
final class FeatureFlag
{
    /** @var array<string,bool>|null */
    private static ?array $cache = null;

    public static function enabled(string $key, bool $default = false): bool
    {
        self::load();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, bool $enabled): void
    {
        Database::query(
            'INSERT INTO feature_flags (flag_key, is_enabled, updated_at) VALUES (?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled), updated_at = NOW()',
            [$key, $enabled ? 1 : 0]
        );
        if (self::$cache !== null) {
            self::$cache[$key] = $enabled;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function all(): array
    {
        try {
            return Database::select('SELECT flag_key, is_enabled, description, updated_at FROM feature_flags ORDER BY flag_key');
        } catch (Throwable) {
            return [];
        }
    }

    private static function load(): void
    {
        if (self::$cache !== null) {
            return;
        }
        self::$cache = [];
        try {
            foreach (Database::select('SELECT flag_key, is_enabled FROM feature_flags') as $row) {
                self::$cache[(string) $row['flag_key']] = (bool) $row['is_enabled'];
            }
        } catch (Throwable) {
            self::$cache = [];
        }
    }
}
