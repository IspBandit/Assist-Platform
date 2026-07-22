<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class CaravanPark extends Model
{
    protected static string $table = 'caravan_parks';
    protected static bool $softDeletes = true;

    /**
     * Public stay directory. Sponsored records remain clearly labelled and
     * never displace distance ordering inside their own result tier.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function searchStays(?int $townId, ?float $lat, ?float $lng, ?string $stayType, ?string $priceType, int $limit = 60): array
    {
        $where = ["cp.status = 'active'", 'cp.public_page_enabled = 1', 'cp.deleted_at IS NULL'];
        $params = [];
        if ($stayType !== null) {
            $where[] = 'cp.stay_type = ?';
            $params[] = $stayType;
        }
        if ($priceType !== null) {
            $where[] = 'cp.price_type = ?';
            $params[] = $priceType;
        }

        if ($townId !== null && ($lat === null || $lng === null)) {
            $town = Database::selectOne('SELECT latitude, longitude FROM towns WHERE id = ? AND is_active = 1', [$townId]);
            if ($town !== null && is_numeric($town['latitude']) && is_numeric($town['longitude'])) {
                $lat = (float) $town['latitude'];
                $lng = (float) $town['longitude'];
            } else {
                $where[] = 'cp.town_id = ?';
                $params[] = $townId;
            }
        }

        $distanceSql = 'NULL AS distance_km';
        $order = 'cp.is_featured DESC, cp.name ASC';
        if ($lat !== null && $lng !== null) {
            $distanceSql = '(6371 * ACOS(LEAST(1, GREATEST(-1, '
                . 'COS(RADIANS(?)) * COS(RADIANS(cp.latitude)) * COS(RADIANS(cp.longitude) - RADIANS(?)) '
                . '+ SIN(RADIANS(?)) * SIN(RADIANS(cp.latitude)))))) AS distance_km';
            array_unshift($params, $lat, $lng, $lat);
            $where[] = 'cp.latitude IS NOT NULL AND cp.longitude IS NOT NULL';
            $order = 'cp.is_featured DESC, distance_km ASC, cp.name ASC';
        }

        $limit = max(1, min(100, $limit));
        return Database::select(
            'SELECT cp.*, t.name AS town_name, s.abbreviation AS state_abbr, ' . $distanceSql . ' '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN states s ON s.id = cp.state_id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY ' . $order . ' LIMIT ' . $limit,
            $params
        );
    }

    public static function uniqueSlug(string $source): string
    {
        $base = str_slug($source) ?: 'park';
        $slug = $base;
        $n = 1;
        while ((int) Database::scalar('SELECT COUNT(*) FROM caravan_parks WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public static function adminListing(?string $status, string $search, int $limit, int $offset): array
    {
        $where = ['cp.deleted_at IS NULL'];
        $params = [];
        if ($status !== null && $status !== '') {
            $where[] = 'cp.status = ?';
            $params[] = $status;
        }
        if ($search !== '') {
            $where[] = '(cp.name LIKE ? OR cp.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*) FROM caravan_parks cp' . $clause, $params);
        $rows = Database::select(
            'SELECT cp.id, cp.name, cp.slug, cp.status, cp.public_page_enabled, t.name AS town_name '
            . 'FROM caravan_parks cp LEFT JOIN towns t ON t.id = cp.town_id'
            . $clause . ' ORDER BY cp.name LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    public static function adminFind(int $id): ?array
    {
        return Database::selectOne(
            'SELECT cp.*, t.name AS town_name, r.name AS region_name, s.name AS state_name '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN regions r ON r.id = cp.region_id '
            . 'LEFT JOIN states s ON s.id = cp.state_id '
            . 'WHERE cp.id = ? AND cp.deleted_at IS NULL',
            [$id]
        );
    }

    public static function findPublicBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT cp.*, t.name AS town_name, t.slug AS town_slug, r.name AS region_name '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN regions r ON r.id = cp.region_id '
            . "WHERE cp.slug = ? AND cp.status = 'active' AND cp.public_page_enabled = 1 AND cp.deleted_at IS NULL",
            [$slug]
        );
    }

    /** The park a given user manages (first linked park). */
    public static function forUser(int $userId): ?array
    {
        return Database::selectOne(
            'SELECT cp.* FROM caravan_parks cp '
            . 'INNER JOIN caravan_park_users cpu ON cpu.park_id = cp.id '
            . 'WHERE cpu.user_id = ? AND cp.deleted_at IS NULL ORDER BY cp.id LIMIT 1',
            [$userId]
        );
    }

    public static function userManages(int $userId, int $parkId): bool
    {
        return (int) Database::scalar(
            'SELECT COUNT(*) FROM caravan_park_users WHERE user_id = ? AND park_id = ?',
            [$userId, $parkId]
        ) > 0;
    }

    /** @return array<int,array<string,mixed>> */
    public static function documents(int $parkId): array
    {
        return Database::select(
            'SELECT * FROM caravan_park_documents WHERE park_id = ? ORDER BY id DESC',
            [$parkId]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function serviceDayRequests(int $parkId): array
    {
        return Database::select(
            'SELECT sdr.*, c.name AS category_name FROM caravan_park_service_day_requests sdr '
            . 'LEFT JOIN service_categories c ON c.id = sdr.category_id '
            . 'WHERE sdr.park_id = ? ORDER BY sdr.created_at DESC',
            [$parkId]
        );
    }

    /** Public runs near a park (same town or region), open for registration. */
    public static function nearbyRuns(?int $townId, ?int $regionId): array
    {
        if ($townId === null && $regionId === null) {
            return [];
        }
        $where = ["sr.is_public = 1", 'sr.deleted_at IS NULL', "sr.status IN ('forming','confirmed','limited')"];
        $params = [];
        $clauses = [];
        if ($regionId !== null) {
            $clauses[] = 'sr.region_id = ?';
            $params[] = $regionId;
        }
        if ($townId !== null) {
            $clauses[] = 'EXISTS (SELECT 1 FROM service_run_towns srt WHERE srt.run_id = sr.id AND srt.town_id = ?)';
            $params[] = $townId;
        }
        $where[] = '(' . implode(' OR ', $clauses) . ')';

        return Database::select(
            'SELECT sr.id, sr.title, sr.slug, sr.status, sr.start_date, sr.appointments_total, sr.bookings_count, '
            . 'p.business_name FROM service_runs sr LEFT JOIN providers p ON p.id = sr.provider_id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY sr.start_date IS NULL, sr.start_date',
            $params
        );
    }
}
