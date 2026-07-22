<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Services\ProviderCoverage;

final class Provider extends Model
{
    protected static string $table = 'providers';
    protected static bool $softDeletes = true;

    /**
     * Admin listing with optional status filter and search term.
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    /**
     * Filterable admin listing of providers.
     *
     * @param array<string,mixed> $filters Supported keys: status, search, town
     *   (base-town name LIKE), category (service category id), state (state id,
     *   matched on base town OR region), source ('claimed'|'unclaimed'),
     *   verified (truthy), featured (truthy).
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public static function adminListing(array $filters, int $limit, int $offset): array
    {
        $where = ['p.deleted_at IS NULL'];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.business_name LIKE ? OR p.email LIKE ? OR p.contact_name LIKE ? OR p.phone LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $town = trim((string) ($filters['town'] ?? ''));
        if ($town !== '') {
            $where[] = 't.name LIKE ?';
            $params[] = '%' . $town . '%';
        }

        $categoryId = (int) ($filters['category'] ?? 0);
        if ($categoryId > 0) {
            $where[] = 'EXISTS (SELECT 1 FROM provider_services ps WHERE ps.provider_id = p.id AND ps.category_id = ?)';
            $params[] = $categoryId;
        }

        $stateId = (int) ($filters['state'] ?? 0);
        if ($stateId > 0) {
            $where[] = '(t.state_id = ? OR r.state_id = ?)';
            $params[] = $stateId;
            $params[] = $stateId;
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source === 'unclaimed') {
            $where[] = 'p.is_unclaimed = 1';
        } elseif ($source === 'claimed') {
            $where[] = 'p.is_unclaimed = 0';
        }

        if (!empty($filters['verified'])) {
            $where[] = 'p.is_verified = 1';
        }
        if (!empty($filters['featured'])) {
            $where[] = 'p.is_featured = 1';
        }

        $joins = ' LEFT JOIN towns t ON t.id = p.base_town_id LEFT JOIN regions r ON r.id = p.region_id';
        $clause = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(*) FROM providers p' . $joins . $clause, $params);
        $rows = Database::select(
            'SELECT p.id, p.business_name, p.slug, p.status, p.is_verified, p.is_featured, p.is_demo, p.is_unclaimed, '
            . 'p.subscription_state, p.is_founding_provider, t.name AS town_name, p.created_at '
            . 'FROM providers p' . $joins
            . $clause . ' ORDER BY p.created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null Provider with town/region names for admin. */
    public static function adminFind(int $id): ?array
    {
        return Database::selectOne(
            'SELECT p.*, t.name AS town_name, r.name AS region_name, u.email AS user_email, u.name AS user_name '
            . 'FROM providers p LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN regions r ON r.id = p.region_id LEFT JOIN users u ON u.id = p.user_id '
            . 'WHERE p.id = ? AND p.deleted_at IS NULL',
            [$id]
        );
    }

    /**
     * Public directory of active providers, optional town/category/search filters.
     *
     * @return array{rows:array<int,array<string,mixed>>,total:int}
     */
    public static function publicDirectory(?int $townId, ?int $categoryId, string $search, int $limit, int $offset): array
    {
        $where = ["p.status = 'active'", 'p.deleted_at IS NULL'];
        $params = [];
        $join = 'LEFT JOIN towns t ON t.id = p.base_town_id';

        if ($categoryId !== null) {
            $join .= ' INNER JOIN provider_services ps ON ps.provider_id = p.id AND ps.category_id = ?';
            $params[] = $categoryId;
        }
        if ($townId !== null && $townId > 0) {
            $where[] = ProviderCoverage::sqlServesTown();
            array_push($params, ...ProviderCoverage::servesTownParams($townId));
        }
        if ($search !== '') {
            $where[] = 'p.business_name LIKE ?';
            $params[] = '%' . $search . '%';
        }
        $clause = ' WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(DISTINCT p.id) FROM providers p ' . $join . $clause, $params);
        $rows = Database::select(
            'SELECT DISTINCT p.id, p.business_name, p.slug, p.description, p.service_model, '
            . 'p.is_verified, p.is_featured, p.is_founding_provider, p.is_unclaimed, p.coverage_confidence, t.name AS town_name '
            . 'FROM providers p ' . $join . $clause
            . ' ORDER BY p.is_featured DESC, p.is_verified DESC, p.business_name LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );

        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:int} */
    public static function brandDirectory(int $brandId, ?int $townId, ?int $categoryId, string $search, int $limit, int $offset): array
    {
        $where = ["pbl.status = 'active'", 'pbl.search_visible = 1', 'pbl.deleted_at IS NULL', "p.status = 'active'", 'p.deleted_at IS NULL', 'pbl.brand_id = ?'];
        $params = [$brandId];
        $joins = ' JOIN provider_brand_listings pbl ON pbl.provider_id = p.id LEFT JOIN towns t ON t.id = p.base_town_id ';
        if ($categoryId !== null && $categoryId > 0) {
            $joins .= ' JOIN provider_brand_category_assignments pbca ON pbca.listing_id = pbl.id AND pbca.category_id = ? ';
            array_unshift($params, $categoryId);
        }
        if ($townId !== null && $townId > 0) {
            $where[] = ProviderCoverage::sqlServesTown();
            array_push($params, ...ProviderCoverage::servesTownParams($townId));
        }
        if ($search !== '') {
            $where[] = '(pbl.display_name LIKE ? OR p.business_name LIKE ? OR p.description LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        $clause = ' WHERE ' . implode(' AND ', $where);
        $total = (int) Database::scalar('SELECT COUNT(DISTINCT p.id) FROM providers p ' . $joins . $clause, $params);
        $rows = Database::select(
            'SELECT DISTINCT p.id, pbl.slug, pbl.display_name AS business_name, p.description, p.service_model, '
            . 'pbl.is_verified, pbl.is_featured, p.is_founding_provider, p.is_unclaimed, p.coverage_confidence, t.name AS town_name '
            . 'FROM providers p ' . $joins . $clause
            . ' ORDER BY pbl.is_featured DESC, pbl.is_verified DESC, pbl.display_name LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
        return ['rows' => $rows, 'total' => $total];
    }

    /** @return array<string,mixed>|null */
    public static function findPublicBrandBySlug(int $brandId, string $slug): ?array
    {
        return Database::selectOne(
            'SELECT p.*, pbl.slug AS brand_slug, pbl.display_name AS brand_display_name, pbl.is_verified AS brand_verified, '
            . 'pbl.is_featured AS brand_featured, pbl.seo_title AS brand_seo_title, pbl.seo_description AS brand_seo_description, '
            . 't.name AS town_name, t.slug AS town_slug, t.primary_postcode AS town_postcode, t.latitude AS town_lat, t.longitude AS town_lng, '
            . 's.abbreviation AS state_abbr, r.name AS region_name, r.slug AS region_slug '
            . 'FROM provider_brand_listings pbl JOIN providers p ON p.id = pbl.provider_id '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id LEFT JOIN states s ON s.id = t.state_id LEFT JOIN regions r ON r.id = p.region_id '
            . "WHERE pbl.brand_id = ? AND pbl.slug = ? AND pbl.status = 'active' AND pbl.search_visible = 1 AND pbl.deleted_at IS NULL AND p.status = 'active' AND p.deleted_at IS NULL",
            [$brandId, $slug]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public static function brandServices(int $brandId, int $providerId): array
    {
        return Database::select(
            'SELECT c.id AS category_id, c.name, c.category_key AS slug, a.is_verified, a.confidence '
            . 'FROM provider_brand_listings l JOIN provider_brand_category_assignments a ON a.listing_id = l.id '
            . 'JOIN brand_provider_categories c ON c.id = a.category_id '
            . 'WHERE l.brand_id = ? AND l.provider_id = ? AND c.is_active = 1 ORDER BY c.sort_order, c.name',
            [$brandId, $providerId]
        );
    }

    /** @return array<string,mixed>|null Active provider profile by slug. */
    public static function findPublicBySlug(string $slug): ?array
    {
        return Database::selectOne(
            'SELECT p.*, t.name AS town_name, t.slug AS town_slug, '
            . 't.primary_postcode AS town_postcode, t.latitude AS town_lat, t.longitude AS town_lng, '
            . 's.abbreviation AS state_abbr, r.name AS region_name, r.slug AS region_slug '
            . 'FROM providers p LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'LEFT JOIN regions r ON r.id = p.region_id '
            . "WHERE p.slug = ? AND p.status = 'active' AND p.deleted_at IS NULL",
            [$slug]
        );
    }

    /** @return array<int,array<string,mixed>> Service categories for a provider. */
    public static function services(int $providerId): array
    {
        return Database::select(
            'SELECT ps.id, ps.category_id, c.name, c.slug FROM provider_services ps '
            . 'JOIN service_categories c ON c.id = ps.category_id '
            . 'WHERE ps.provider_id = ? ORDER BY c.name',
            [$providerId]
        );
    }

    /**
     * Active providers linked to a service category, with their match type
     * (is_inferred = 0 direct match, 1 possible match). Direct matches first.
     * Optionally scoped to a town (base town) or region.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forCategory(int $categoryId, ?int $townId = null, ?int $regionId = null, int $limit = 60): array
    {
        $where = ['ps.category_id = ?', "p.status = 'active'", 'p.deleted_at IS NULL'];
        $params = [$categoryId];
        if ($townId !== null && $townId > 0) {
            $where[] = ProviderCoverage::sqlServesTown();
            array_push($params, ...ProviderCoverage::servesTownParams($townId));
        }
        if ($regionId !== null && $regionId > 0) {
            $where[] = ProviderCoverage::sqlServesRegion();
            array_push($params, ...ProviderCoverage::servesRegionParams($regionId));
        }

        return Database::select(
            'SELECT p.id, p.business_name, p.slug, p.service_model, p.is_verified, p.is_featured, '
            . 'p.is_founding_provider, p.is_unclaimed, ps.is_inferred, '
            . 't.name AS town_name, t.slug AS town_slug, t.latitude AS town_lat, t.longitude AS town_lng, '
            . 's.abbreviation AS state_abbr '
            . 'FROM provider_services ps '
            . 'JOIN providers p ON p.id = ps.provider_id '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ps.is_inferred ASC, p.is_featured DESC, p.is_verified DESC, p.business_name '
            . 'LIMIT ' . $limit,
            $params
        );
    }

    /**
     * Every business relevant to a town, ranked by how directly it serves it:
     *   relevance 0 — based in the town, or its service areas explicitly cover it;
     *   relevance 1 — a mobile/both operator elsewhere in the same region (travels);
     *   relevance 2 — a workshop elsewhere in the same region (a nearby option).
     *
     * Passing the town's region surfaces the wider pool of operators who realistically
     * service the town, not just the handful with that exact base town.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function inTown(int $townId, ?int $regionId = null, int $limit = 90): array
    {
        $hasRegion = $regionId !== null && $regionId > 0;
        $covers = ProviderCoverage::sqlServesTown();
        $params = array_merge(
            ProviderCoverage::servesTownParams($townId),
            ProviderCoverage::servesTownParams($townId)
        );
        $regionClause = '';
        if ($hasRegion) {
            $regionClause = ' OR ' . ProviderCoverage::sqlServesRegion();
            array_push($params, ...ProviderCoverage::servesRegionParams($regionId));
        }

        return Database::select(
            'SELECT DISTINCT p.id, p.business_name, p.slug, p.service_model, p.is_verified, p.is_featured, '
            . 'p.is_founding_provider, p.is_unclaimed, p.coverage_confidence, p.description, p.street_address, '
            . 't.name AS town_name, t.latitude AS town_lat, t.longitude AS town_lng, s.abbreviation AS state_abbr, '
            . 'CASE WHEN ' . $covers . ' THEN 0 '
            . "WHEN p.service_model IN ('mobile','both') THEN 1 ELSE 2 END AS relevance "
            . 'FROM providers p '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL "
            . 'AND (' . $covers . $regionClause . ') '
            . 'ORDER BY relevance ASC, p.is_featured DESC, p.is_verified DESC, p.business_name LIMIT ' . $limit,
            $params
        );
    }

    /**
     * Active providers serving a region (service areas, not just base town).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function inRegion(int $regionId, int $limit = 60): array
    {
        $sql = ProviderCoverage::sqlServesRegion();
        $params = ProviderCoverage::servesRegionParams($regionId);

        return Database::select(
            'SELECT DISTINCT p.id, p.business_name, p.slug, p.service_model, p.is_verified, p.is_featured, '
            . 'p.is_founding_provider, p.is_unclaimed, p.coverage_confidence, p.description, t.name AS town_name '
            . 'FROM providers p '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL AND {$sql} "
            . 'ORDER BY p.is_featured DESC, p.is_verified DESC, p.business_name LIMIT ' . $limit,
            $params
        );
    }

    /**
     * Providers serving a town for the homepage "near you" module.
     * Featured and claimed listings appear first. Discovered directory entries
     * fill otherwise-empty coverage and remain explicitly marked unclaimed.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function forHomeNearTown(
        int $townId,
        ?int $regionId = null,
        int $maxFeatured = 4,
        int $maxTotal = 6,
    ): array {
        if ($townId <= 0) {
            return [];
        }

        $all = self::inTown($townId, $regionId, 60);
        $claimed = array_values(array_filter(
            $all,
            static fn (array $p): bool => (int) ($p['is_unclaimed'] ?? 0) === 0,
        ));
        $discovered = array_values(array_filter(
            $all,
            static fn (array $p): bool => (int) ($p['is_unclaimed'] ?? 0) === 1,
        ));

        $out = [];
        $seen = [];

        foreach ($claimed as $row) {
            if (empty($row['is_featured']) || count($out) >= $maxFeatured) {
                continue;
            }
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $row['slot'] = 'featured';
            $out[] = $row;
        }

        foreach ($claimed as $row) {
            if (count($out) >= $maxTotal) {
                break;
            }
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $row['slot'] = 'local';
            $out[] = $row;
        }

        foreach ($discovered as $row) {
            if (count($out) >= $maxTotal) {
                break;
            }
            $id = (int) $row['id'];
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $row['slot'] = 'discovered';
            $out[] = $row;
        }

        return $out;
    }

    /** @return array<int,array<string,mixed>> Service areas for a provider. */
    public static function areas(int $providerId): array
    {
        return Database::select(
            'SELECT a.*, t.name AS town_name, r.name AS region_name, s.name AS state_name '
            . 'FROM provider_service_areas a '
            . 'LEFT JOIN towns t ON t.id = a.town_id '
            . 'LEFT JOIN regions r ON r.id = a.region_id '
            . 'LEFT JOIN states s ON s.id = a.state_id '
            . 'WHERE a.provider_id = ? ORDER BY a.area_type',
            [$providerId]
        );
    }
}
