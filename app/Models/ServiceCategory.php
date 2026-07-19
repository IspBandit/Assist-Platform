<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class ServiceCategory extends Model
{
    protected static string $table = 'service_categories';

    /** @return array<int,array<string,mixed>> All categories with parent name (admin). */
    public static function listing(): array
    {
        return Database::select(
            'SELECT c.*, p.name AS parent_name FROM service_categories c '
            . 'LEFT JOIN service_categories p ON p.id = c.parent_id '
            . 'ORDER BY COALESCE(c.parent_id, c.id), c.parent_id IS NOT NULL, c.sort_order, c.name'
        );
    }

    /** @return array<int,array<string,mixed>> Active top-level categories. */
    public static function activeTopLevel(): array
    {
        return Database::select(
            'SELECT * FROM service_categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY sort_order, name'
        );
    }

    /** @return array<int,array<string,mixed>> Active children of a category. */
    public static function activeChildren(int $parentId): array
    {
        return Database::select(
            'SELECT * FROM service_categories WHERE is_active = 1 AND parent_id = ? ORDER BY sort_order, name',
            [$parentId]
        );
    }

    /** @return array<string,mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT * FROM service_categories WHERE slug = ? AND is_active = 1',
            [$slug]
        );
    }

    /** @return array<int,array<string,mixed>> Categories selectable as a parent (excludes self). */
    public static function parentOptions(?int $excludeId = null): array
    {
        $sql = 'SELECT id, name FROM service_categories WHERE parent_id IS NULL';
        $params = [];
        if ($excludeId !== null) {
            $sql .= ' AND id <> ?';
            $params[] = $excludeId;
        }
        $sql .= ' ORDER BY sort_order, name';
        return Database::select($sql, $params);
    }
}
