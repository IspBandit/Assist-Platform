<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Read-only Admin API access to brand-scoped providers (CORE-011 Increment 4).
 */
final class AdminApiProviderService
{
    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function list(Request $request): array
    {
        $brandId = AdminApiBrandScope::brandId();
        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $filters = $this->parseFilters($request);

        $where = ['p.deleted_at IS NULL', 'pbl.brand_id = ?', 'pbl.deleted_at IS NULL'];
        $params = [$brandId];

        if ($afterId !== null) {
            $where[] = 'p.id < ?';
            $params[] = $afterId;
        }

        $statusFilter = AdminApiLifecycle::providerFilterClause($filters['status']);
        if ($filters['status'] !== '' && $statusFilter === null) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['status' => ['Status or lifecycle filter is not recognised.']]
            );
        }
        if ($statusFilter !== null) {
            $where[] = $statusFilter['clause'];
            array_push($params, ...$statusFilter['params']);
        }

        $search = $filters['q'];
        if ($search !== '') {
            $where[] = '(p.business_name LIKE ? OR p.email LIKE ? OR p.contact_name LIKE ? OR pbl.display_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like, $like);
        }

        $town = $filters['town'];
        if ($town !== '') {
            $where[] = 't.name LIKE ?';
            $params[] = '%' . $town . '%';
        }

        if ($filters['state_id'] > 0) {
            $where[] = '(t.state_id = ? OR r.state_id = ?)';
            array_push($params, $filters['state_id'], $filters['state_id']);
        }

        $joins = ' INNER JOIN provider_brand_listings pbl ON pbl.provider_id = p.id '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN regions r ON r.id = p.region_id '
            . 'LEFT JOIN states s ON s.id = t.state_id';
        $clause = ' WHERE ' . implode(' AND ', $where);
        $fetchLimit = $limit + 1;

        $rows = Database::select(
            'SELECT p.id, p.business_name, p.slug, p.status, p.is_verified, p.is_featured, p.is_unclaimed, '
            . 'p.subscription_state, p.is_founding_provider, p.created_at, p.updated_at, '
            . 'pbl.slug AS brand_slug, pbl.display_name, pbl.status AS listing_status, pbl.search_visible, '
            . 't.name AS town_name, s.abbreviation AS state_abbr '
            . 'FROM providers p' . $joins . $clause
            . ' ORDER BY p.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);
        $items = array_map(fn (array $row): array => $this->summary($row), $page['items']);

        return [
            'items' => $items,
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'brand_id' => AdminApiBrandScope::brandId(),
                'brand_key' => AdminApiBrandScope::brand()->id(),
            ],
            'links' => [
                'next' => $page['next_cursor'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function show(int $id): array
    {
        $brandId = AdminApiBrandScope::brandId();
        $row = Database::selectOne(
            'SELECT p.*, pbl.slug AS brand_slug, pbl.display_name, pbl.status AS listing_status, '
            . 'pbl.search_visible, pbl.is_verified AS brand_verified, pbl.is_featured AS brand_featured, '
            . 't.name AS town_name, r.name AS region_name, s.name AS state_name, s.abbreviation AS state_abbr '
            . 'FROM providers p '
            . 'INNER JOIN provider_brand_listings pbl ON pbl.provider_id = p.id AND pbl.brand_id = ? AND pbl.deleted_at IS NULL '
            . 'LEFT JOIN towns t ON t.id = p.base_town_id '
            . 'LEFT JOIN regions r ON r.id = p.region_id '
            . 'LEFT JOIN states s ON s.id = t.state_id '
            . 'WHERE p.id = ? AND p.deleted_at IS NULL',
            [$brandId, $id]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return $this->detail($row);
    }

    /**
     * @return array{q:string,status:string,town:string,state_id:int}
     */
    public function parseFilters(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'town' => trim((string) $request->query('town', '')),
            'state_id' => $this->resolveStateId((string) $request->query('state', '')),
        ];
    }

    public function resolveStateId(string $state): int
    {
        $state = trim($state);
        if ($state === '') {
            return 0;
        }

        if (ctype_digit($state)) {
            return (int) $state;
        }

        $resolved = Database::scalar(
            'SELECT id FROM states WHERE abbreviation = ? OR LOWER(name) = LOWER(?) LIMIT 1',
            [strtoupper($state), $state]
        );

        return $resolved !== null ? (int) $resolved : 0;
    }

    /** @param array<string,mixed> $row */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'business_name' => (string) ($row['display_name'] ?? $row['business_name'] ?? ''),
            'slug' => (string) ($row['brand_slug'] ?? $row['slug'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'lifecycle' => AdminApiLifecycle::forProvider($row),
            'is_verified' => (bool) ((int) ($row['is_verified'] ?? 0)),
            'is_featured' => (bool) ((int) ($row['is_featured'] ?? 0)),
            'is_unclaimed' => (bool) ((int) ($row['is_unclaimed'] ?? 0)),
            'town' => $row['town_name'] !== null ? (string) $row['town_name'] : null,
            'state' => $row['state_abbr'] !== null ? (string) $row['state_abbr'] : null,
            'created_at' => $this->iso($row['created_at'] ?? null),
            'updated_at' => $this->iso($row['updated_at'] ?? null),
        ];
    }

    /** @param array<string,mixed> $row */
    private function detail(array $row): array
    {
        $summary = $this->summary($row);

        return array_merge($summary, [
            'canonical_slug' => (string) ($row['slug'] ?? ''),
            'contact_name' => $row['contact_name'] !== null ? (string) $row['contact_name'] : null,
            'email' => $row['email'] !== null ? (string) $row['email'] : null,
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'website' => $row['website'] !== null ? (string) $row['website'] : null,
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'service_model' => $row['service_model'] !== null ? (string) $row['service_model'] : null,
            'street_address' => $row['street_address'] !== null ? (string) $row['street_address'] : null,
            'region' => $row['region_name'] !== null ? (string) $row['region_name'] : null,
            'listing_status' => (string) ($row['listing_status'] ?? ''),
            'search_visible' => (bool) ((int) ($row['search_visible'] ?? 0)),
            'brand_verified' => (bool) ((int) ($row['brand_verified'] ?? 0)),
            'brand_featured' => (bool) ((int) ($row['brand_featured'] ?? 0)),
            'subscription_state' => $row['subscription_state'] !== null ? (string) $row['subscription_state'] : null,
            'is_founding_provider' => (bool) ((int) ($row['is_founding_provider'] ?? 0)),
        ]);
    }

    private function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timestamp = strtotime((string) $value);
        if ($timestamp === false) {
            return null;
        }

        return date('c', $timestamp);
    }
}
