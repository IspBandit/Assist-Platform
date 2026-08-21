<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Read-only Admin API access to stays (`caravan_parks`, ADR 0019).
 */
final class AdminApiStayService
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
        if (!AdminApiBrandScope::staysEnabled()) {
            return $this->emptyPage(AdminApiCursor::limit($request->query('limit')));
        }

        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $filters = $this->parseFilters($request);

        $where = ['cp.deleted_at IS NULL'];
        $params = [];

        if ($afterId !== null) {
            $where[] = 'cp.id < ?';
            $params[] = $afterId;
        }

        $statusFilter = AdminApiLifecycle::stayFilterClause($filters['status']);
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
            $where[] = '(cp.name LIKE ? OR cp.email LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }

        $town = $filters['town'];
        if ($town !== '') {
            $where[] = 't.name LIKE ?';
            $params[] = '%' . $town . '%';
        }

        if ($filters['state_id'] > 0) {
            $where[] = '(cp.state_id = ? OR t.state_id = ?)';
            array_push($params, $filters['state_id'], $filters['state_id']);
        }

        $joins = ' LEFT JOIN towns t ON t.id = cp.town_id LEFT JOIN states s ON s.id = cp.state_id';
        $clause = ' WHERE ' . implode(' AND ', $where);
        $fetchLimit = $limit + 1;

        $rows = Database::select(
            'SELECT cp.id, cp.name, cp.slug, cp.status, cp.public_page_enabled, cp.stay_type, cp.price_type, '
            . 'cp.is_featured, cp.created_at, cp.updated_at, t.name AS town_name, s.abbreviation AS state_abbr '
            . 'FROM caravan_parks cp' . $joins . $clause
            . ' ORDER BY cp.id DESC LIMIT ' . $fetchLimit,
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
                'stays_available' => true,
            ],
            'links' => [
                'next' => $page['next_cursor'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function show(int $id): array
    {
        AdminApiBrandScope::assertStaysEnabled();

        $row = Database::selectOne(
            'SELECT cp.*, t.name AS town_name, r.name AS region_name, s.name AS state_name, s.abbreviation AS state_abbr '
            . 'FROM caravan_parks cp '
            . 'LEFT JOIN towns t ON t.id = cp.town_id '
            . 'LEFT JOIN regions r ON r.id = cp.region_id '
            . 'LEFT JOIN states s ON s.id = cp.state_id '
            . 'WHERE cp.id = ? AND cp.deleted_at IS NULL',
            [$id]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return $this->detail($row);
    }

    /**
     * @return array{q:string,status:string,town:string,state_id:int}
     */
    public function parseFilters(Request $request): array
    {
        $providerService = new AdminApiProviderService();

        return [
            'q' => trim((string) $request->query('q', '')),
            'status' => strtolower(trim((string) $request->query('status', ''))),
            'town' => trim((string) $request->query('town', '')),
            'state_id' => $providerService->resolveStateId((string) $request->query('state', '')),
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    private function emptyPage(int $limit): array
    {
        return [
            'items' => [],
            'meta' => [
                'count' => 0,
                'limit' => $limit,
                'has_more' => false,
                'next_cursor' => null,
                'brand_id' => AdminApiBrandScope::brandId(),
                'brand_key' => AdminApiBrandScope::brand()->id(),
                'stays_available' => false,
            ],
            'links' => [
                'next' => null,
            ],
        ];
    }

    /** @param array<string,mixed> $row */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'lifecycle' => AdminApiLifecycle::forStay($row),
            'stay_type' => $row['stay_type'] !== null ? (string) $row['stay_type'] : null,
            'price_type' => $row['price_type'] !== null ? (string) $row['price_type'] : null,
            'public_page_enabled' => (bool) ((int) ($row['public_page_enabled'] ?? 0)),
            'is_featured' => (bool) ((int) ($row['is_featured'] ?? 0)),
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
            'address' => $row['address'] !== null ? (string) $row['address'] : null,
            'phone' => $row['phone'] !== null ? (string) $row['phone'] : null,
            'email' => $row['email'] !== null ? (string) $row['email'] : null,
            'website' => $row['website'] !== null ? (string) $row['website'] : null,
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'region' => $row['region_name'] !== null ? (string) $row['region_name'] : null,
            'state_name' => $row['state_name'] !== null ? (string) $row['state_name'] : null,
            'number_of_sites' => isset($row['number_of_sites']) ? (int) $row['number_of_sites'] : null,
            'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
            'source_type' => $row['source_type'] !== null ? (string) $row['source_type'] : null,
            'external_id' => $row['external_id'] !== null ? (string) $row['external_id'] : null,
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
