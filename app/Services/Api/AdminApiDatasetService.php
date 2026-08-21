<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\GovernmentDatasetService;
use Throwable;

/**
 * Government dataset catalogue read/update and sync runs (Option B Increment D).
 *
 * `POST .../sync` creates a sync_run row and immediately executes
 * GovernmentDatasetService::fetchDataset (review-first staging). Does not
 * auto-publish facilities.
 */
final class AdminApiDatasetService
{
    /** @var list<string> */
    private const PATCH_FIELDS = [
        'title',
        'coverage',
        'jurisdiction',
        'licence',
        'attribution',
        'endpoint_url',
        'source_url',
        'source_format',
        'update_frequency',
        'settings',
        'default_facility_type',
        'is_enabled',
        'auto_update_enabled',
        'catalogue_status',
        'notes',
        'duplicate_rules',
        'record_count',
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

            if ($field === 'duplicate_rules') {
                $updates[] = 'duplicate_rules_json = ?';
                $params[] = json_encode(is_array($input['duplicate_rules']) ? $input['duplicate_rules'] : [], JSON_THROW_ON_ERROR);
                continue;
            }

            if ($field === 'is_enabled' || $field === 'auto_update_enabled') {
                $updates[] = $field . ' = ?';
                $params[] = self::boolish($input[$field]) ? 1 : 0;
                continue;
            }

            if ($field === 'catalogue_status') {
                $status = trim((string) $input['catalogue_status']);
                if (!in_array($status, ['planned', 'indexed', 'active', 'paused', 'retired'], true)) {
                    throw new AdminApiException(
                        422,
                        'validation_failed',
                        'Validation failed.',
                        ['catalogue_status' => ['Must be planned, indexed, active, paused or retired.']]
                    );
                }
                $updates[] = 'catalogue_status = ?';
                $params[] = $status;
                continue;
            }

            if ($field === 'record_count') {
                $updates[] = 'record_count = ?';
                $params[] = max(0, (int) $input['record_count']);
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

        if (!Database::tableExists('traveller_facility_import_jobs')) {
            throw new AdminApiException(
                503,
                'unavailable',
                'Facility import jobs are not available. Apply Assist AI/DATA-012 migrations.'
            );
        }

        $now = date('Y-m-d H:i:s');
        $runId = Database::insert(
            'INSERT INTO government_dataset_sync_runs (dataset_id, status, started_at, created_at) VALUES (?, ?, ?, ?)',
            [$id, 'running', $now, $now]
        );

        AdminApiAudit::record(
            'dataset.sync_started',
            'government_dataset',
            $id,
            null,
            ['sync_run_id' => $runId],
            $request
        );

        $brandId = AdminApiBrandScope::brandId();
        $userId = AdminApiContext::userId();
        $useFixture = self::boolish($request->input('fixture', $request->query('fixture', false)));

        try {
            $gov = new GovernmentDatasetService();
            $result = $useFixture
                ? $gov->importFixture($id, $brandId, $userId)
                : $gov->fetchDataset($id, $brandId, $userId);

            $found = (int) ($result['found'] ?? 0);
            Database::query(
                'UPDATE government_dataset_sync_runs
                 SET status = ?, finished_at = ?, records_fetched = ?, error_message = NULL
                 WHERE id = ?',
                ['completed', date('Y-m-d H:i:s'), $found, $runId]
            );
            try {
                Database::query(
                    'UPDATE government_datasets
                     SET last_downloaded_at = NOW(), last_checked_at = NOW(), record_count = ?, updated_at = NOW()
                     WHERE id = ?',
                    [$found, $id]
                );
            } catch (Throwable) {
                // Pre-117 deployments may lack DATA-011A columns; sync_run still completed.
            }

            AdminApiAudit::record(
                'dataset.sync_completed',
                'government_dataset',
                $id,
                null,
                [
                    'sync_run_id' => $runId,
                    'job_id' => (int) ($result['job_id'] ?? 0),
                    'found' => $found,
                    'new' => (int) ($result['new'] ?? 0),
                    'fixture' => $useFixture,
                ],
                $request
            );
        } catch (Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 1000);
            Database::query(
                'UPDATE government_dataset_sync_runs
                 SET status = ?, finished_at = ?, error_message = ?
                 WHERE id = ?',
                ['failed', date('Y-m-d H:i:s'), $message, $runId]
            );

            AdminApiAudit::record(
                'dataset.sync_failed',
                'government_dataset',
                $id,
                null,
                ['sync_run_id' => $runId, 'error' => $message],
                $request
            );

            throw new AdminApiException(
                422,
                'validation_failed',
                'Dataset sync failed.',
                ['sync' => [$message]]
            );
        }

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
            'name' => (string) ($row['title'] ?? ''),
            'title' => (string) ($row['title'] ?? ''),
            'publisher' => (string) ($row['publisher'] ?? ''),
            'jurisdiction' => isset($row['jurisdiction']) && $row['jurisdiction'] !== null ? (string) $row['jurisdiction'] : null,
            'fetch_method' => (string) ($row['fetch_method'] ?? ''),
            'source_format' => isset($row['source_format']) && $row['source_format'] !== null ? (string) $row['source_format'] : null,
            'connector_key' => (string) ($row['connector_key'] ?? ''),
            'is_enabled' => (bool) ((int) ($row['is_enabled'] ?? 0)),
            'auto_update_enabled' => (bool) ((int) ($row['auto_update_enabled'] ?? 0)),
            'catalogue_status' => (string) ($row['catalogue_status'] ?? 'planned'),
            'trust_level' => (string) ($row['trust_policy'] ?? ''),
            'last_checked_at' => $row['last_checked_at'] ?? null,
            'last_downloaded_at' => $row['last_downloaded_at'] ?? null,
            'last_imported_at' => $row['last_imported_at'] ?? null,
            'record_count' => isset($row['record_count']) && $row['record_count'] !== null ? (int) $row['record_count'] : null,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function detail(array $row): array
    {
        return array_merge($this->summary($row), [
            'coverage' => $row['coverage'] !== null ? (string) $row['coverage'] : null,
            'entity_types' => $this->decodeJson($row['record_types_json'] ?? null),
            'record_types' => $this->decodeJson($row['record_types_json'] ?? null),
            'duplicate_rules' => $this->decodeJson($row['duplicate_rules_json'] ?? null),
            'licence' => $row['licence'] !== null ? (string) $row['licence'] : null,
            'attribution' => $row['attribution'] !== null ? (string) $row['attribution'] : null,
            'trust_policy' => (string) ($row['trust_policy'] ?? ''),
            'update_frequency' => isset($row['update_frequency']) && $row['update_frequency'] !== null ? (string) $row['update_frequency'] : null,
            'endpoint_url' => $row['endpoint_url'] !== null ? (string) $row['endpoint_url'] : null,
            'api_url' => $row['endpoint_url'] !== null ? (string) $row['endpoint_url'] : null,
            'source_url' => isset($row['source_url']) && $row['source_url'] !== null ? (string) $row['source_url'] : null,
            'settings' => $this->decodeJson($row['settings_json'] ?? null),
            'import_mapping' => $this->decodeJson($row['settings_json'] ?? null),
            'default_facility_type' => (string) ($row['default_facility_type'] ?? ''),
            'notes' => isset($row['notes']) && $row['notes'] !== null ? (string) $row['notes'] : null,
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
