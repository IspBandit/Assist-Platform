<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Read-only Admin API access to traveller facilities (ADR 0019, Option B Increment G).
 */
final class AdminApiFacilityService
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
        if (!Database::tableExists('traveller_facilities')) {
            return $this->emptyPage(AdminApiCursor::limit($request->query('limit')));
        }

        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $brandId = AdminApiBrandScope::brandId();

        $where = ['tf.deleted_at IS NULL', '(tf.brand_id = ? OR tf.brand_id IS NULL)'];
        $params = [$brandId];

        if ($afterId !== null) {
            $where[] = 'tf.id < ?';
            $params[] = $afterId;
        }

        $status = strtolower(trim((string) $request->query('status', '')));
        if ($status !== '') {
            $where[] = 'tf.status = ?';
            $params[] = $status;
        }

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $where[] = '(tf.name LIKE ? OR tf.locality LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT tf.*, s.abbreviation AS state_abbr '
            . 'FROM traveller_facilities tf '
            . 'LEFT JOIN states s ON s.id = tf.state_id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY tf.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => $this->summary($row), $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'brand_id' => $brandId,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @return array<string,mixed> */
    public function show(int $id): array
    {
        $row = $this->find($id);

        return $this->detail($row);
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        if (!Database::tableExists('traveller_facilities')) {
            throw new AdminApiException(404, 'not_found', 'Facility not found.');
        }

        $brandId = AdminApiBrandScope::brandId();
        $row = Database::selectOne(
            'SELECT tf.*, s.abbreviation AS state_abbr, s.name AS state_name '
            . 'FROM traveller_facilities tf '
            . 'LEFT JOIN states s ON s.id = tf.state_id '
            . 'WHERE tf.id = ? AND tf.deleted_at IS NULL '
            . 'AND (tf.brand_id = ? OR tf.brand_id IS NULL)',
            [$id, $brandId]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Facility not found.');
        }

        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'facility_type' => (string) ($row['facility_type'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'verification_status' => (string) ($row['verification_status'] ?? ''),
            'locality' => $row['locality'] !== null ? (string) $row['locality'] : null,
            'state' => $row['state_abbr'] !== null ? (string) $row['state_abbr'] : null,
            'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
            'created_at' => $row['created_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function detail(array $row): array
    {
        return array_merge($this->summary($row), [
            'formatted_address' => $row['formatted_address'] !== null ? (string) $row['formatted_address'] : null,
            'state_name' => $row['state_name'] !== null ? (string) $row['state_name'] : null,
            'operating_status' => (string) ($row['operating_status'] ?? ''),
            'opening_hours' => $row['opening_hours'] !== null ? (string) $row['opening_hours'] : null,
            'accessibility_notes' => $row['accessibility_notes'] !== null ? (string) $row['accessibility_notes'] : null,
            'source_key' => $row['source_key'] !== null ? (string) $row['source_key'] : null,
            'source_record_id' => $row['source_record_id'] !== null ? (string) $row['source_record_id'] : null,
            'confidence' => (int) ($row['confidence'] ?? 0),
            'linked_provider_id' => $row['linked_provider_id'] !== null ? (string) $row['linked_provider_id'] : null,
        ]);
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
                'source' => 'traveller_facilities_missing',
            ],
            'links' => ['next' => null],
        ];
    }
}
