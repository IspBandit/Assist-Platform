<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Government dataset catalogue read/update and sync run stubs (Option B Increment D).
 */
final class AdminApiDatasetService
{
    /** @var list<string> */
    private const PATCH_FIELDS = [
        'title',
        'coverage',
        'licence',
        'attribution',
        'endpoint_url',
        'settings',
        'default_facility_type',
        'is_enabled',
    ];

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function list(Request $request): array
    {
        if (!Database::tableExists('government_datasets')) {
            return $this->emptyPage(AdminApiCursor::limit($request->query('limit')));
        }

        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $enabled = trim((string) $request->query('enabled', ''));

        $where = ['archived_at IS NULL'];
        $params = [];

        if ($enabled === '1' || $enabled === 'true') {
            $where[] = 'is_enabled = 1';
        } elseif ($enabled === '0' || $enabled === 'false') {
            $where[] = 'is_enabled = 0';
        }

        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT * FROM government_datasets WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
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

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function patch(int $id, array $input, Request $request): array
    {
        $row = $this->find($id);
        $updates = [];
        $params = [];

        foreach (self::PATCH_FIELDS as $field) {
            if (!array_key_exists($field, $input)) {
                continue;
            }

            if ($field === 'settings') {
                $updates[] = 'settings_json = ?';
                $params[] = json_encode(is_array($input['settings']) ? $input['settings'] : [], JSON_THROW_ON_ERROR);
                continue;
            }

            if ($field === 'is_enabled') {
                $updates[] = 'is_enabled = ?';
                $params[] = self::boolish($input['is_enabled']) ? 1 : 0;
                continue;
            }

            $updates[] = $field . ' = ?';
            $params[] = $input[$field];
        }

        if ($updates === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['body' => ['No recognised fields to update.']]
            );
        }

        $updates[] = 'updated_at = NOW()';
        $params[] = $id;

        Database::query(
            'UPDATE government_datasets SET ' . implode(', ', $updates) . ' WHERE id = ?',
            $params
        );

        AdminApiAudit::record(
            'dataset.updated',
            'government_dataset',
            $id,
            ['dataset_key' => (string) $row['dataset_key']],
            ['fields' => array_keys(array_intersect_key($input, array_flip(self::PATCH_FIELDS)))],
            $request
        );

        return $this->detail($this->find($id));
    }

    /** @return array<string,mixed> */
    public function enqueueSync(int $id, Request $request): array
    {
        $dataset = $this->find($id);

        if (!Database::tableExists('government_dataset_sync_runs')) {
            throw new AdminApiException(503, 'unavailable', 'Dataset sync runs are not available.');
        }

        $now = date('Y-m-d H:i:s');
        $runId = Database::insert(
            'INSERT INTO government_dataset_sync_runs (dataset_id, status, started_at, created_at) VALUES (?, ?, ?, ?)',
            [$id, 'queued', null, $now]
        );

        AdminApiAudit::record(
            'dataset.sync_queued',
            'government_dataset',
            $id,
            null,
            ['sync_run_id' => $runId],
            $request
        );

        return [
            'dataset_id' => (string) $id,
            'dataset_key' => (string) $dataset['dataset_key'],
            'sync_run' => $this->mapSyncRun($this->findSyncRun($runId)),
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function syncHistory(int $id, Request $request): array
    {
        $this->find($id);
        $limit = AdminApiCursor::limit($request->query('limit'));

        if (!Database::tableExists('government_dataset_sync_runs')) {
            return $this->emptyPage($limit);
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['dataset_id = ?'];
        $params = [$id];

        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT * FROM government_dataset_sync_runs WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => $this->mapSyncRun($row), $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'dataset_id' => (string) $id,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        if (!Database::tableExists('government_datasets')) {
            throw new AdminApiException(404, 'not_found', 'Dataset not found.');
        }

        $row = Database::selectOne('SELECT * FROM government_datasets WHERE id = ?', [$id]);
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Dataset not found.');
        }

        return $row;
    }

    /** @return array<string,mixed> */
    private function findSyncRun(int $id): array
    {
        $row = Database::selectOne('SELECT * FROM government_dataset_sync_runs WHERE id = ?', [$id]);
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Sync run not found.');
        }

        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'dataset_key' => (string) $row['dataset_key'],
            'title' => (string) ($row['title'] ?? ''),
            'publisher' => (string) ($row['publisher'] ?? ''),
            'fetch_method' => (string) ($row['fetch_method'] ?? ''),
            'connector_key' => (string) ($row['connector_key'] ?? ''),
            'is_enabled' => (bool) ((int) ($row['is_enabled'] ?? 0)),
            'last_checked_at' => $row['last_checked_at'] ?? null,
            'last_imported_at' => $row['last_imported_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function detail(array $row): array
    {
        return array_merge($this->summary($row), [
            'coverage' => $row['coverage'] !== null ? (string) $row['coverage'] : null,
            'record_types' => $this->decodeJson($row['record_types_json'] ?? null),
            'licence' => $row['licence'] !== null ? (string) $row['licence'] : null,
            'attribution' => $row['attribution'] !== null ? (string) $row['attribution'] : null,
            'trust_policy' => (string) ($row['trust_policy'] ?? ''),
            'endpoint_url' => $row['endpoint_url'] !== null ? (string) $row['endpoint_url'] : null,
            'settings' => $this->decodeJson($row['settings_json'] ?? null),
            'default_facility_type' => (string) ($row['default_facility_type'] ?? ''),
            'last_error' => $row['last_error'] !== null ? (string) $row['last_error'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
        ]);
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapSyncRun(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'dataset_id' => (string) $row['dataset_id'],
            'status' => (string) $row['status'],
            'started_at' => $row['started_at'] ?? null,
            'finished_at' => $row['finished_at'] ?? null,
            'records_fetched' => (int) ($row['records_fetched'] ?? 0),
            'error_message' => $row['error_message'] !== null ? (string) $row['error_message'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @return array<string,mixed> */
    private function decodeJson(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
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
                'source' => 'government_datasets_missing',
            ],
            'links' => ['next' => null],
        ];
    }

    private static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
