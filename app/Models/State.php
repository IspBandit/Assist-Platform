<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class State extends Model
{
    protected static string $table = 'states';

    /** @return array<int,array<string,mixed>> */
    public static function allWithCountry(): array
    {
        return Database::select(
            'SELECT s.*, c.name AS country_name, '
            . '(SELECT COUNT(*) FROM regions r WHERE r.state_id = s.id) AS region_count, '
            . '(SELECT COUNT(*) FROM towns t WHERE t.state_id = s.id) AS town_count '
            . 'FROM states s LEFT JOIN countries c ON c.id = s.country_id '
            . 'ORDER BY s.name'
        );
    }

    /** @return array<string,mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return Database::selectOne('SELECT * FROM states WHERE slug = ? AND is_active = 1', [$slug]);
    }
}
