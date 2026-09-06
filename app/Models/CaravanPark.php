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
    public static function searchStays(?int $townId, ?float $lat, ?float $lng, ?string $stayType, ?string $priceType, int $maxDistanceKm = 150, int $limit = 60, array $requiredFacilities = []): array
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
            $town = Database::selectOne('SELECT latitude, longitude, coordinate_confidence FROM towns WHERE id = ? AND is_active = 1', [$townId]);
            if ($town !== null && in_array(($town['coordinate_confidence'] ?? 'unverified'), ['authoritative', 'statistical'], true)
                && is_numeric($town['latitude']) && is_numeric($town['longitude'])) {
                $lat = (float) $town['latitude'];
                $lng = (float) $town['longitude'];
            } else {
                $where[] = 'cp.town_id = ?';
                $params[] = $townId;
            }
        }

        $distanceSql = 'NULL AS distance_km';
        $having = '';
        $order = 'cp.is_featured DESC, cp.name ASC';
        if ($lat !== null && $lng !== null) {
            $distanceSql = '(6371 * ACOS(LEAST(1, GREATEST(-1, '
                . 'COS(RADIANS(?)) * COS(RADIANS(cp.latitude)) * COS(RADIANS(cp.longitude) - RADIANS(?)) '
                . '+ SIN(RADIANS(?)) * SIN(RADIANS(cp.latitude)))))) AS distance_km';
            array_unshift($params, $lat, $lng, $lat);
            $where[] = 'cp.latitude IS NOT NULL AND cp.longitude IS NOT NULL';
            $having = ' HAVING distance_km <= ?';
            $params[] = max(1, min(500, $maxDistanceKm));
            $order = 'cp.is_featured DESC, distance_km ASC, cp.name ASC';
        }

        $requestedLimit = max(1, min(100, $limit));
        // Facility resolution happens against provenance-ranked claims after
        // the geospatial query. Pull a wider in-radius candidate set so a
        // facility filter cannot hide valid matches merely because 60 other
        // stays appeared first.
        $queryLimit = $requiredFacilities === [] ? $requestedLimit : 500;
        $rows = Database::select(
            'SELECT cp.*, t.name AS town_name, s.abbreviation AS state_abbr, ' . $distanceSql . ' '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN states s ON s.id = cp.state_id '
            . 'WHERE ' . implode(' AND ', $where) . $having . ' ORDER BY ' . $order . ' LIMIT ' . $queryLimit,
            $params
        );
        if ($requiredFacilities === [] || !Database::tableExists('stay_facility_claims')) {
            return $rows;
        }
        $facilityMap = (new \App\Services\StayFacilityService())->forParks(array_map(static fn(array $row): int => (int)$row['id'], $rows));
        $filtered = array_values(array_filter($rows, static function(array $row) use ($requiredFacilities, $facilityMap): bool {
            $facts = $facilityMap[(int)$row['id']] ?? [];
            foreach ($requiredFacilities as $type) {
                if (!isset($facts[$type]) || !in_array($facts[$type]['facility_status'], ['yes','conditional'], true)) return false;
            }
            return true;
        }));
        return array_slice($filtered, 0, $requestedLimit);
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
            'SELECT cp.*, t.name AS town_name, t.slug AS town_slug, r.name AS region_name, s.name AS state_name '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN regions r ON r.id = cp.region_id '
            . 'LEFT JOIN states s ON s.id = cp.state_id '
            . "WHERE cp.slug = ? AND cp.status = 'active' AND cp.public_page_enabled = 1 AND cp.deleted_at IS NULL",
            [$slug]
        );
    }

    /** Build the visible/SEO location from stored facts, even without a town. */
    public static function publicLocation(array $park): string
    {
        $parts = [];
        foreach (['town_name', 'region_name', 'state_name'] as $key) {
            $value = trim((string) ($park[$key] ?? ''));
            if ($value !== '' && !in_array($value, $parts, true)) {
                $parts[] = $value;
            }
        }
        return implode(', ', $parts);
    }

    /**
     * Resolve a named stay/campground as a search origin. This is deliberately
     * read-only and restricted to active public records with complete
     * coordinates. A conservative fuzzy score tolerates a small spelling
     * mistake but rejects weak or ambiguous matches.
     *
     * @return array<string,mixed>|null
     */
    public static function resolvePublicLandmark(string $query): ?array
    {
        $parsed = Town::parseSearchQuery($query);
        $core = self::normaliseLandmarkName($parsed['term']);
        $tokens = preg_split('/\s+/u', $core, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $first = (string) ($tokens[0] ?? '');
        if (mb_strlen($first) < 4) {
            return null;
        }

        $select = 'SELECT cp.id AS park_id, cp.name, cp.slug, cp.town_id, cp.region_id, cp.state_id, '
            . 'cp.latitude, cp.longitude, t.name AS town_name, s.name AS state_name, s.abbreviation AS state_abbr '
            . 'FROM caravan_parks cp LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN states s ON s.id = cp.state_id '
            . "WHERE cp.status = 'active' AND cp.public_page_enabled = 1 AND cp.deleted_at IS NULL "
            . 'AND cp.latitude IS NOT NULL AND cp.longitude IS NOT NULL ';
        $stateSql = '';
        $stateParams = [];
        if ($parsed['state'] !== null) {
            $stateSql = 'AND s.abbreviation = ? ';
            $stateParams[] = $parsed['state'];
        }

        $rows = Database::select(
            $select . $stateSql
            . "AND (SOUNDEX(SUBSTRING_INDEX(cp.name, ' ', 1)) = SOUNDEX(?) OR LOWER(cp.name) LIKE LOWER(?)) "
            . 'ORDER BY cp.is_featured DESC, cp.name LIMIT 250',
            array_merge($stateParams, [$first, '%' . $core . '%'])
        );
        // Some names start with a generic prefix (for example "The"). A
        // state-qualified query can safely use a bounded state fallback.
        if ($rows === [] && $parsed['state'] !== null) {
            $rows = Database::select(
                $select . $stateSql . 'ORDER BY cp.is_featured DESC, cp.name LIMIT 2500',
                $stateParams
            );
        }

        $ranked = [];
        foreach ($rows as $row) {
            $score = self::landmarkMatchScore($core, (string) ($row['name'] ?? ''));
            if ($score >= 0.78) {
                $row['landmark_match_score'] = $score;
                $ranked[] = $row;
            }
        }
        usort($ranked, static function (array $a, array $b): int {
            return ((float) $b['landmark_match_score']) <=> ((float) $a['landmark_match_score']);
        });
        if ($ranked === []) {
            return null;
        }
        if (isset($ranked[1])
            && abs((float) $ranked[0]['landmark_match_score'] - (float) $ranked[1]['landmark_match_score']) < 0.03
            && self::normaliseLandmarkName((string) $ranked[0]['name']) !== self::normaliseLandmarkName((string) $ranked[1]['name'])) {
            return null;
        }

        return $ranked[0];
    }

    public static function landmarkMatchScore(string $query, string $candidate): float
    {
        $query = self::normaliseLandmarkName($query);
        $candidate = self::normaliseLandmarkName($candidate);
        if ($query === '' || $candidate === '') {
            return 0.0;
        }
        if ($query === $candidate) {
            return 1.0;
        }
        if (str_contains($candidate, $query) || str_contains($query, $candidate)) {
            return 0.95;
        }

        $queryTokens = preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $candidateTokens = preg_split('/\s+/u', $candidate, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($queryTokens === [] || $candidateTokens === []) {
            return 0.0;
        }

        $total = 0.0;
        foreach ($queryTokens as $queryToken) {
            $best = 0.0;
            foreach ($candidateTokens as $candidateToken) {
                $length = max(strlen($queryToken), strlen($candidateToken));
                if ($length === 0) {
                    continue;
                }
                $best = max($best, 1.0 - (levenshtein($queryToken, $candidateToken) / $length));
            }
            $total += $best;
        }

        return $total / count($queryTokens);
    }

    private static function normaliseLandmarkName(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = (string) preg_replace('/[^a-z0-9]+/u', ' ', $value);
        $value = (string) preg_replace(
            '/\b(?:the|camping ground|campground|camping area|camp site|campsite|caravan park|holiday park|recreation area|rest area)\b/u',
            ' ',
            $value
        );
        return trim((string) preg_replace('/\s+/u', ' ', $value));
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
