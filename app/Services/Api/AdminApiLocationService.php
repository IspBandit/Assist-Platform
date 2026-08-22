<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Read-only location taxonomy for Assist RIC Directory pickers (Increment I).
 *
 * Global states/regions/towns — not brand-scoped. Towns always use cursor pagination.
 */
final class AdminApiLocationService
{
    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    public function listStates(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('states');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'states_missing', 'states');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['1=1'];
        $params = [];
        $activeOnly = $this->activeFilter($request, true);
        if ($activeOnly === true) {
            $where[] = 'is_active = 1';
        } elseif ($activeOnly === false) {
            $where[] = 'is_active = 0';
        }
        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, country_id, name, slug, abbreviation, is_active '
            . 'FROM states WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );
        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'country_id' => (int) ($row['country_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'abbreviation' => $row['abbreviation'] !== null ? (string) $row['abbreviation'] : null,
                'is_active' => (bool) ($row['is_active'] ?? false),
            ], $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'resource' => 'states',
                'writable' => false,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    public function listRegions(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('regions');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'regions_missing', 'regions');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['1=1'];
        $params = [];
        $stateId = $this->optionalPositiveInt($request->query('state_id'), 'state_id');
        if ($stateId !== null) {
            $where[] = 'state_id = ?';
            $params[] = $stateId;
        }
        $activeOnly = $this->activeFilter($request, true);
        if ($activeOnly === true) {
            $where[] = 'is_active = 1';
        } elseif ($activeOnly === false) {
            $where[] = 'is_active = 0';
        }
        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, state_id, name, slug, is_active, is_featured '
            . 'FROM regions WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );
        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'state_id' => (int) ($row['state_id'] ?? 0),
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'is_active' => (bool) ($row['is_active'] ?? false),
                'is_featured' => (bool) ($row['is_featured'] ?? false),
            ], $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'resource' => 'regions',
                'state_id' => $stateId,
                'writable' => false,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    public function listTowns(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('towns');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'towns_missing', 'towns');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['1=1'];
        $params = [];
        $stateId = $this->optionalPositiveInt($request->query('state_id'), 'state_id');
        $regionId = $this->optionalPositiveInt($request->query('region_id'), 'region_id');
        if ($stateId !== null) {
            $where[] = 't.state_id = ?';
            $params[] = $stateId;
        }
        if ($regionId !== null) {
            $where[] = 't.region_id = ?';
            $params[] = $regionId;
        }
        $activeOnly = $this->activeFilter($request, true);
        if ($activeOnly === true) {
            $where[] = 't.is_active = 1';
        } elseif ($activeOnly === false) {
            $where[] = 't.is_active = 0';
        }
        $search = trim((string) $request->query('q', $request->query('search', '')));
        if ($search !== '') {
            $where[] = '(t.name LIKE ? OR t.slug LIKE ? OR t.primary_postcode LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like, $like);
        }
        if ($afterId !== null) {
            $where[] = 't.id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT t.id, t.state_id, t.region_id, t.name, t.slug, t.primary_postcode, t.latitude, t.longitude, '
            . 't.is_active, t.is_launch_town, s.abbreviation AS state_abbr '
            . 'FROM towns t JOIN states s ON s.id = t.state_id WHERE ' . implode(' AND ', $where)
            . ' ORDER BY t.id DESC LIMIT ' . $fetchLimit,
            $params
        );
        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'state_id' => (int) ($row['state_id'] ?? 0),
                'region_id' => isset($row['region_id']) && $row['region_id'] !== null ? (int) $row['region_id'] : null,
                'name' => (string) ($row['name'] ?? ''),
                'slug' => (string) ($row['slug'] ?? ''),
                'primary_postcode' => $row['primary_postcode'] !== null ? (string) $row['primary_postcode'] : null,
                'state_abbr' => isset($row['state_abbr']) ? (string) $row['state_abbr'] : null,
                'latitude' => isset($row['latitude']) && $row['latitude'] !== null ? (float) $row['latitude'] : null,
                'longitude' => isset($row['longitude']) && $row['longitude'] !== null ? (float) $row['longitude'] : null,
                'is_active' => (bool) ($row['is_active'] ?? false),
                'is_launch_town' => (bool) ($row['is_launch_town'] ?? false),
            ], $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'resource' => 'towns',
                'state_id' => $stateId,
                'region_id' => $regionId,
                'writable' => false,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /**
     * @return bool|null true=active only, false=inactive only, null=all
     */
    private function activeFilter(Request $request, bool $defaultActiveOnly): ?bool
    {
        $raw = $request->query('active');
        if ($raw === null || $raw === '') {
            return $defaultActiveOnly ? true : null;
        }
        $active = strtolower(trim((string) $raw));
        if (!in_array($active, ['0', '1', 'true', 'false', 'all'], true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['active' => ['active must be 1, 0, true, false, or all.']]
            );
        }
        if ($active === 'all') {
            return null;
        }

        return in_array($active, ['1', 'true'], true);
    }

    private function optionalPositiveInt(mixed $raw, string $field): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        $value = (int) $raw;
        if ($value < 1) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                [$field => [$field . ' must be a positive integer.']]
            );
        }

        return $value;
    }

    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    private function emptyPage(int $limit, string $source, string $resource): array
    {
        return [
            'items' => [],
            'meta' => [
                'count' => 0,
                'limit' => $limit,
                'has_more' => false,
                'next_cursor' => null,
                'sparse' => true,
                'source' => $source,
                'resource' => $resource,
                'writable' => false,
            ],
            'links' => ['next' => null],
        ];
    }
}
