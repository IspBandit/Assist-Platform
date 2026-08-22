<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class MotorsportVenue extends Model
{
    protected static string $table = 'motorsport_venues';

    /** @return array<int,array<string,mixed>> */
    public static function publicDirectory(string $family = '', string $jurisdiction = ''): array
    {
        $where = ['v.is_public=1'];
        $params = [];
        if ($family !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM motorsport_venue_families vf WHERE vf.venue_id=v.id AND vf.family_key=?)';
            $params[] = $family;
        }
        if ($jurisdiction !== '' && $jurisdiction !== 'AUS') {
            $where[] = 'v.jurisdiction_code=?';
            $params[] = $jurisdiction;
        }

        return Database::select(
            'SELECT v.*,(SELECT GROUP_CONCAT(vf.family_key ORDER BY vf.family_key SEPARATOR ",") FROM motorsport_venue_families vf WHERE vf.venue_id=v.id) AS family_keys '
            . 'FROM motorsport_venues v WHERE ' . implode(' AND ', $where) . ' '
            . "ORDER BY FIELD(v.venue_type,'permanent','temporary','event_route','club_network'),v.jurisdiction_code,v.name",
            $params
        );
    }
}
