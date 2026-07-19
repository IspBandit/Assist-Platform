<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class Town extends Model
{
    protected static string $table = 'towns';

    /**
     * Paginated admin listing with region/state names and optional filters.
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public static function listing(?int $stateId, ?int $regionId, int $limit, int $offset): array
    {
        $where = [];
        $params = [];
        if ($stateId !== null) {
            $where[] = 't.state_id = ?';
            $params[] = $stateId;
        }
        if ($regionId !== null) {
            $where[] = 't.region_id = ?';
            $params[] = $regionId;
        }
        $clause = $where === [] ? '' : (' WHERE ' . implode(' AND ', $where));

        $total = (int) Database::scalar('SELECT COUNT(*) FROM towns t' . $clause, $params);

        $rows = Database::select(
            'SELECT t.*, r.name AS region_name, s.name AS state_name, s.abbreviation AS state_abbr '
            . 'FROM towns t JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = t.region_id'
            . $clause
            . ' ORDER BY t.name LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function findActiveById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        return Database::selectOne(
            'SELECT t.*, r.name AS region_name, r.slug AS region_slug, '
            . 's.name AS state_name, s.slug AS state_slug, s.abbreviation AS state_abbr '
            . 'FROM towns t JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = t.region_id '
            . 'WHERE t.id = ? AND t.is_active = 1',
            [$id]
        );
    }

    /** Default town for homepage provider spotlight when no GPS is available. */
    public static function defaultLaunchTown(): ?array
    {
        return Database::selectOne(
            'SELECT t.*, r.name AS region_name, r.slug AS region_slug, '
            . 's.name AS state_name, s.slug AS state_slug, s.abbreviation AS state_abbr '
            . 'FROM towns t JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = t.region_id '
            . 'WHERE t.is_active = 1 AND t.is_launch_town = 1 '
            . 'ORDER BY t.is_featured DESC, t.name LIMIT 1'
        );
    }

    /** @return array<string,mixed>|null */
    public static function findActiveBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT t.*, r.name AS region_name, r.slug AS region_slug, '
            . 's.name AS state_name, s.slug AS state_slug, s.abbreviation AS state_abbr '
            . 'FROM towns t JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = t.region_id '
            . 'WHERE t.slug = ? AND t.is_active = 1',
            [$slug]
        );
    }

    /**
     * Resolve a free-text town, suburb or postcode query to active localities,
     * best match first. Postcodes match primary_postcode or the postcodes table;
     * names match exactly, then by prefix, then anywhere in the name (so suburbs
     * and partial typing work). An optional state suffix ("Parramatta, NSW") is
     * recognised.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function searchActive(string $query, int $limit = 10): array
    {
        $parsed = self::parseSearchQuery($query);
        $term = $parsed['term'];
        if ($term === '') {
            return [];
        }

        $limit = max(1, min(25, $limit));
        $select = 'SELECT t.id, t.name, t.slug, t.primary_postcode, t.region_id, t.latitude, t.longitude, '
            . 'r.name AS region_name, r.slug AS region_slug, s.name AS state_name, s.abbreviation AS state_abbr '
            . 'FROM towns t JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = t.region_id '
            . 'WHERE t.is_active = 1 AND ';
        $order = ' ORDER BY t.is_launch_town DESC, t.is_featured DESC, LENGTH(t.name), t.name LIMIT ' . $limit;
        $stateClause = '';
        $stateParams = [];
        if ($parsed['state'] !== null) {
            $stateClause = ' AND s.abbreviation = ?';
            $stateParams = [$parsed['state']];
        }

        if (preg_match('/^\d{3,4}$/', $term)) {
            return Database::select(
                $select . '(t.primary_postcode = ? OR EXISTS (SELECT 1 FROM postcodes pc '
                . 'WHERE pc.town_id = t.id AND pc.code = ?))' . $stateClause . $order,
                array_merge([$term, $term], $stateParams)
            );
        }

        $exact = Database::select(
            $select . 'LOWER(t.name) = LOWER(?)' . $stateClause . $order,
            array_merge([$term], $stateParams)
        );
        if ($exact !== []) {
            return $exact;
        }

        $prefix = Database::select(
            $select . 't.name LIKE ?' . $stateClause . $order,
            array_merge([$term . '%'], $stateParams)
        );
        if ($prefix !== []) {
            return $prefix;
        }

        return Database::select(
            $select . 't.name LIKE ?' . $stateClause . $order,
            array_merge(['%' . $term . '%'], $stateParams)
        );
    }

    /**
     * @return array{term:string,state:?string}
     */
    public static function parseSearchQuery(string $query): array
    {
        $query = trim($query);
        $state = null;
        if (preg_match('/,\s*([A-Za-z]{2,3})\s*$/', $query, $m)) {
            $state = strtoupper($m[1]);
            $query = trim(substr($query, 0, -strlen($m[0])));
        }

        return ['term' => $query, 'state' => $state];
    }

    /**
     * Resolve a device GPS coordinate to the nearest active town/suburb that has
     * coordinates, using the haversine great-circle distance. A generous
     * bounding box keeps the scan fast; if nothing falls inside it (very remote
     * fix) we fall back to scanning all geocoded active towns.
     *
     * @return array<string,mixed>|null
     */
    public static function nearestActive(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        $select = 'SELECT t.id, t.name, t.slug, t.primary_postcode, t.region_id, t.state_id, t.latitude, t.longitude, '
            . 'r.name AS region_name, r.slug AS region_slug, s.name AS state_name, s.abbreviation AS state_abbr, '
            . '(6371 * acos(LEAST(1, '
            . 'cos(radians(?)) * cos(radians(t.latitude)) * cos(radians(t.longitude) - radians(?)) '
            . '+ sin(radians(?)) * sin(radians(t.latitude))))) AS distance_km '
            . 'FROM towns t JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = t.region_id '
            . 'WHERE t.is_active = 1 AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL ';
        $orderLimit = ' ORDER BY distance_km ASC LIMIT 1';

        // ~5 degrees (~550 km) bounding box first.
        $box = 5.0;
        $town = Database::selectOne(
            $select . 'AND t.latitude BETWEEN ? AND ? AND t.longitude BETWEEN ? AND ?' . $orderLimit,
            [$lat, $lng, $lat, $lat - $box, $lat + $box, $lng - $box, $lng + $box]
        );
        if ($town !== null) {
            return $town;
        }

        // Fallback: scan all geocoded active towns.
        return Database::selectOne($select . $orderLimit, [$lat, $lng, $lat]);
    }

    /**
     * Active towns within a region, most-relevant first (launch/featured towns,
     * then alphabetical). Limited because a region can contain hundreds of
     * localities; the public region page shows this subset plus a total count.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function activeInRegion(int $regionId, int $limit = 120): array
    {
        return Database::select(
            'SELECT id, name, slug, primary_postcode, is_launch_town FROM towns '
            . 'WHERE region_id = ? AND is_active = 1 '
            . 'ORDER BY is_launch_town DESC, is_featured DESC, name LIMIT ' . max(1, $limit),
            [$regionId]
        );
    }

    /** Total active towns in a region. */
    public static function countActiveInRegion(int $regionId): int
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM towns WHERE region_id = ? AND is_active = 1',
            [$regionId]
        );
    }

    /** @return array<int,array<string,mixed>> Neighbouring towns ordered by distance. */
    public static function neighbours(int $townId): array
    {
        return Database::select(
            'SELECT t.id, t.name, t.slug, tn.distance_km FROM town_neighbours tn '
            . 'JOIN towns t ON t.id = tn.neighbour_town_id '
            . 'WHERE tn.town_id = ? AND t.is_active = 1 ORDER BY tn.distance_km IS NULL, tn.distance_km LIMIT 8',
            [$townId]
        );
    }
}
