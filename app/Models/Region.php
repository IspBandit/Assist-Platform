<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Region extends Model
{
    protected static string $table = 'regions';

    /** @return array<int,array<string,mixed>> Regions with state name + town counts. */
    public static function listing(?int $stateId = null): array
    {
        $sql = 'SELECT r.*, s.name AS state_name, s.slug AS state_slug, '
            . '(SELECT COUNT(*) FROM towns t WHERE t.region_id = r.id) AS town_count '
            . 'FROM regions r JOIN states s ON s.id = r.state_id';
        $params = [];
        if ($stateId !== null) {
            $sql .= ' WHERE r.state_id = ?';
            $params[] = $stateId;
        }
        $sql .= ' ORDER BY s.name, r.name';
        return Database::select($sql, $params);
    }

    /** @return array<int,array<string,mixed>> Active, featured-first regions for the public index. */
    public static function publicListing(): array
    {
        return Database::select(
            'SELECT r.*, s.name AS state_name, s.slug AS state_slug, '
            . '(SELECT COUNT(*) FROM towns t WHERE t.region_id = r.id AND t.is_active = 1) AS town_count '
            . 'FROM regions r JOIN states s ON s.id = r.state_id '
            . 'WHERE r.is_active = 1 ORDER BY r.is_featured DESC, r.name'
        );
    }

    /** @return array<string,mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT r.*, s.name AS state_name, s.slug AS state_slug, s.abbreviation AS state_abbr '
            . 'FROM regions r JOIN states s ON s.id = r.state_id '
            . 'WHERE r.slug = ? AND r.is_active = 1',
            [$slug]
        );
    }
}
