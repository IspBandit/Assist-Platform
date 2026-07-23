<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class User extends Model
{
    protected static string $table = 'users';
    protected static bool $softDeletes = true;

    public static function findByEmail(string $email): ?array
    {
        return self::findBy('email', strtolower(trim($email)));
    }

    /** @return array<int,array<string,mixed>> roles for the user */
    public static function roles(int $userId): array
    {
        $sql = 'SELECT r.* FROM roles r '
            . 'INNER JOIN user_roles ur ON ur.role_id = r.id '
            . 'WHERE ur.user_id = ?';
        return Database::select($sql, [$userId]);
    }

    /** @return array<int,string> role slugs */
    public static function roleSlugs(int $userId): array
    {
        return array_column(self::roles($userId), 'slug');
    }

    /** @return array<int,string> global and brand-scoped role slugs */
    public static function roleSlugsForBrand(int $userId, int $brandId): array
    {
        $sql = 'SELECT DISTINCT r.slug FROM roles r '
            . 'LEFT JOIN user_roles ur ON ur.role_id = r.id AND ur.user_id = ? '
            . 'LEFT JOIN user_brand_roles ubr ON ubr.role_id = r.id AND ubr.user_id = ? AND ubr.brand_id = ? '
            . 'WHERE ur.user_id IS NOT NULL OR ubr.user_id IS NOT NULL';
        return array_column(Database::select($sql, [$userId, $userId, $brandId]), 'slug');
    }

    /** @return array<int,string> distinct permission slugs across the user's roles */
    public static function permissions(int $userId): array
    {
        $sql = 'SELECT DISTINCT p.slug FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'INNER JOIN user_roles ur ON ur.role_id = rp.role_id '
            . 'WHERE ur.user_id = ?';
        return array_column(Database::select($sql, [$userId]), 'slug');
    }

    /** @return array<int,string> permissions granted globally or for one brand */
    public static function permissionsForBrand(int $userId, int $brandId): array
    {
        $sql = 'SELECT DISTINCT p.slug FROM permissions p '
            . 'INNER JOIN role_permissions rp ON rp.permission_id = p.id '
            . 'INNER JOIN roles r ON r.id = rp.role_id '
            . 'LEFT JOIN user_roles ur ON ur.role_id = r.id AND ur.user_id = ? '
            . 'LEFT JOIN user_brand_roles ubr ON ubr.role_id = r.id AND ubr.user_id = ? AND ubr.brand_id = ? '
            . 'WHERE ur.user_id IS NOT NULL OR ubr.user_id IS NOT NULL';
        return array_column(Database::select($sql, [$userId, $userId, $brandId]), 'slug');
    }

    public static function assignRole(int $userId, int $roleId): void
    {
        Database::query(
            'INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())',
            [$userId, $roleId]
        );
    }

    public static function assignRoleBySlug(int $userId, string $slug): void
    {
        $role = Database::selectOne('SELECT id FROM roles WHERE slug = ?', [$slug]);
        if ($role !== null) {
            self::assignRole($userId, (int) $role['id']);
        }
    }

    public static function recordLogin(int $userId, string $ip, string $userAgent, bool $success): void
    {
        Database::query(
            'INSERT INTO user_login_history (user_id, ip_address, user_agent, was_successful, created_at) '
            . 'VALUES (?, ?, ?, ?, NOW())',
            [$userId, $ip, $userAgent, $success ? 1 : 0]
        );
    }

    public static function touchLastLogin(int $userId): void
    {
        self::update($userId, ['last_login_at' => date('Y-m-d H:i:s')]);
    }
}
