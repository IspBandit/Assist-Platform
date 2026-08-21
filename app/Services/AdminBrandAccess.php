<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Database;
use App\Models\User;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandRegistry;
use RuntimeException;

final class AdminBrandAccess
{
    /** @return array<string,Brand> */
    public static function availableBrands(int $userId): array
    {
        $registry = BrandRegistry::fromArray((array) config('brands.registry', []));
        $roles = User::roleSlugs($userId);
        if (array_intersect(['super-administrator', 'administrator', 'platform-administrator'], $roles) !== []) {
            return $registry->all();
        }

        if (!Database::tableExists('user_brand_roles')) {
            return [];
        }
        $ids = array_map('intval', array_column(Database::select(
            'SELECT DISTINCT brand_id FROM user_brand_roles WHERE user_id = ?',
            [$userId]
        ), 'brand_id'));
        $allowed = [];
        foreach ($ids as $id) {
            $brand = $registry->forDatabaseId($id);
            if ($brand !== null) { $allowed[$brand->id()] = $brand; }
        }
        return $allowed;
    }

    public static function canAccess(int $userId, Brand $brand): bool
    {
        return isset(self::availableBrands($userId)[$brand->id()]);
    }

    public static function issue(int $userId, Brand $source, Brand $target, string $returnPath = '/admin'): string
    {
        if (!self::canAccess($userId, $target)) {
            throw new RuntimeException('You do not have access to that brand.');
        }
        if (!Database::tableExists('admin_brand_handoff_tokens')) {
            throw new RuntimeException('Run the latest database migration before switching brands.');
        }
        $returnPath = self::safeReturnPath($returnPath);
        $token = bin2hex(random_bytes(32));
        Database::query('DELETE FROM admin_brand_handoff_tokens WHERE expires_at < NOW() OR consumed_at IS NOT NULL');
        Database::insert(
            'INSERT INTO admin_brand_handoff_tokens (token_hash, user_id, source_brand_id, target_brand_id, return_path, expires_at, created_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE), NOW())',
            [hash('sha256', $token), $userId, $source->databaseId(), $target->databaseId(), $returnPath]
        );
        return $token;
    }

    /** @return array{user_id:int,return_path:string}|null */
    public static function consume(string $token, Brand $target): ?array
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token) || !Database::tableExists('admin_brand_handoff_tokens')) { return null; }
        Database::beginTransaction();
        try {
            $row = Database::selectOne(
                'SELECT id, user_id, return_path FROM admin_brand_handoff_tokens WHERE token_hash = ? AND target_brand_id = ? AND consumed_at IS NULL AND expires_at >= NOW() FOR UPDATE',
                [hash('sha256', $token), $target->databaseId()]
            );
            if ($row === null || !self::canAccess((int) $row['user_id'], $target)) {
                Database::rollBack();
                return null;
            }
            Database::query('UPDATE admin_brand_handoff_tokens SET consumed_at = NOW() WHERE id = ?', [(int) $row['id']]);
            Database::commit();
            return ['user_id' => (int) $row['user_id'], 'return_path' => self::safeReturnPath((string) $row['return_path'])];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function safeReturnPath(string $path): string
    {
        return str_starts_with($path, '/admin') && !str_contains($path, "\n") && !str_contains($path, "\r") ? $path : '/admin';
    }
}
