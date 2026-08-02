<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * RIC import package ingest, validation and draft staging (CORE-011 Increment 7).
 */
final class AdminApiImportService
{
    /** @var list<string> */
    private const ENTITY_TYPES = ['provider', 'stay'];

    private AdminApiDraftService $drafts;

    public function __construct(?AdminApiDraftService $drafts = null)
    {
        $this->drafts = $drafts ?? new AdminApiDraftService();
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        $idempotencyKey = AdminApiIdempotency::requireKey($request);

        $checksum = strtolower(trim((string) ($input['checksum'] ?? '')));
        if (!preg_match('/^[a-f0-9]{64}$/', $checksum)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['checksum' => ['Checksum must be a 64-character SHA-256 hex string.']]
            );
        }

        $items = $input['items'] ?? null;
        if (!is_array($items) || $items === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['items' => ['At least one import item is required.']]
            );
        }

        $max = (int) Config::get('admin_api.max_batch_size', 100);
        if (count($items) > $max) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['items' => ['Batch size exceeds max_batch_size (' . $max . ').']]
            );
        }

        $brandId = AdminApiBrandScope::brandId();

        $existing = Database::selectOne(
            'SELECT id FROM api_import_jobs WHERE brand_id = ? AND idempotency_key = ?',
            [$brandId, $idempotencyKey]
        );
        if ($existing !== null) {
            return $this->show((string) $existing['id']);
        }

        $validateOnly = self::boolish($input['validate_only'] ?? $input['dry_run'] ?? false);
        $jobId = AdminApiToken::uuid();
        $now = date('Y-m-d H:i:s');

        Database::beginTransaction();
        try {
            Database::query(
                'INSERT INTO api_import_jobs (id, status, package_checksum, item_count, brand_id, created_by_user_id, '
                . 'created_by_client_id, meta_json, idempotency_key, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $jobId,
                    'received',
                    $checksum,
                    count($items),
                    $brandId,
                    AdminApiContext::userId(),
                    AdminApiContext::clientId(),
                    json_encode($input['meta'] ?? null, JSON_THROW_ON_ERROR),
                    $idempotencyKey,
                    $now,
                    $now,
                ]
            );

            $lineNo = 1;
            foreach ($items as $item) {
                if (!is_array($item)) {
                    throw new AdminApiException(
                        422,
                        'validation_failed',
                        'Validation failed.',
                        ['items.' . ($lineNo - 1) => ['Each item must be an object.']]
                    );
                }

                $entityType = strtolower(trim((string) ($item['entity_type'] ?? '')));
                if (!in_array($entityType, self::ENTITY_TYPES, true)) {
                    throw new AdminApiException(
                        422,
                        'validation_failed',
                        'Validation failed.',
                        ['items.' . ($lineNo - 1) . '.entity_type' => ['Entity type must be provider or stay.']]
                    );
                }

                $payload = is_array($item['payload'] ?? null) ? $item['payload'] : $item;
                unset($payload['entity_type']);

                Database::query(
                    'INSERT INTO api_import_job_items (job_id, line_no, entity_type, status, payload_json) '
                    . 'VALUES (?, ?, ?, ?, ?)',
                    [
                        $jobId,
                        $lineNo,
                        $entityType,
                        'pending',
                        json_encode($payload, JSON_THROW_ON_ERROR),
                    ]
                );
                ++$lineNo;
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AdminApiAudit::record(
            'import.received',
            'api_import_job',
            $jobId,
            null,
            ['item_count' => count($items), 'checksum' => $checksum],
            $request
        );

        if ($validateOnly) {
            return $this->validate($jobId, $request);
        }

        return $this->show($jobId);
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        $job = $this->findScoped($id);
        $items = Database::select(
            'SELECT id, line_no, entity_type, status, payload_json, error_json, draft_id '
            . 'FROM api_import_job_items WHERE job_id = ? ORDER BY line_no ASC',
            [$id]
        );

        return $this->detail($job, $items);
    }

    /** @return array<string,mixed> */
    public function validate(string $id, Request $request): array
    {
        $job = $this->findScoped($id);
        if (in_array((string) $job['status'], ['staged', 'cancelled'], true)) {
            throw new AdminApiException(409, 'conflict', 'Import job cannot be validated in its current status.');
        }

        $items = Database::select(
            'SELECT id, line_no, entity_type, payload_json FROM api_import_job_items WHERE job_id = ? ORDER BY line_no ASC',
            [$id]
        );

        $validCount = 0;
        $invalidCount = 0;

        foreach ($items as $item) {
            $payload = $this->decodeJsonField($item['payload_json']);
            try {
                $this->drafts->validatePayloadPublic((string) $item['entity_type'], $payload);
                Database::query(
                    'UPDATE api_import_job_items SET status = ?, error_json = NULL WHERE id = ?',
                    ['valid', $item['id']]
                );
                ++$validCount;
            } catch (AdminApiException $e) {
                Database::query(
                    'UPDATE api_import_job_items SET status = ?, error_json = ? WHERE id = ?',
                    [
                        'invalid',
                        json_encode(['code' => $e->errorCode(), 'message' => $e->getMessage(), 'fields' => $e->fields()], JSON_THROW_ON_ERROR),
                        $item['id'],
                    ]
                );
                ++$invalidCount;
            }
        }

        $status = $invalidCount > 0 && $validCount === 0 ? 'failed' : 'validated';
        Database::query(
            'UPDATE api_import_jobs SET status = ?, updated_at = NOW() WHERE id = ?',
            [$status, $id]
        );

        AdminApiAudit::record(
            'import.validated',
            'api_import_job',
            $id,
            ['status' => (string) $job['status']],
            ['status' => $status, 'valid' => $validCount, 'invalid' => $invalidCount],
            $request
        );

        return $this->show($id);
    }

    /** @return array<string,mixed> */
    public function stage(string $id, Request $request): array
    {
        $job = $this->findScoped($id);
        if (!in_array((string) $job['status'], ['received', 'validated'], true)) {
            throw new AdminApiException(409, 'conflict', 'Import job must be received or validated before staging.');
        }

        if ((string) $job['status'] === 'received') {
            $this->validate($id, $request);
            $job = $this->findScoped($id);
        }

        $items = Database::select(
            'SELECT * FROM api_import_job_items WHERE job_id = ? AND status = ? ORDER BY line_no ASC',
            [$id, 'valid']
        );

        $staged = 0;
        foreach ($items as $item) {
            try {
                $draftId = $this->drafts->createFromImportItem($item, $id, $request);
                Database::query(
                    'UPDATE api_import_job_items SET status = ?, draft_id = ? WHERE id = ?',
                    ['staged', $draftId, $item['id']]
                );
                ++$staged;
            } catch (\Throwable $e) {
                Database::query(
                    'UPDATE api_import_job_items SET status = ?, error_json = ? WHERE id = ?',
                    [
                        'failed',
                        json_encode(['message' => $e->getMessage()], JSON_THROW_ON_ERROR),
                        $item['id'],
                    ]
                );
            }
        }

        Database::query(
            'UPDATE api_import_jobs SET status = ?, updated_at = NOW() WHERE id = ?',
            ['staged', $id]
        );

        AdminApiAudit::record(
            'import.staged',
            'api_import_job',
            $id,
            ['status' => (string) $job['status']],
            ['status' => 'staged', 'staged_items' => $staged],
            $request
        );

        return $this->show($id);
    }

    /** @return array<string,mixed> */
    public function publish(string $id, Request $request): array
    {
        $job = $this->findScoped($id);
        if ((string) $job['status'] !== 'staged') {
            throw new AdminApiException(409, 'conflict', 'Import job must be staged before publish.');
        }

        $draftIds = Database::select(
            'SELECT draft_id FROM api_import_job_items WHERE job_id = ? AND status = ? AND draft_id IS NOT NULL',
            [$id, 'staged']
        );

        $published = 0;
        foreach ($draftIds as $row) {
            try {
                $this->drafts->approve((string) $row['draft_id'], $request);
                ++$published;
            } catch (\Throwable) {
                // Continue with remaining drafts; job remains partially published.
            }
        }

        Database::query(
            'UPDATE api_import_jobs SET status = ?, updated_at = NOW() WHERE id = ?',
            ['published', $id]
        );

        AdminApiAudit::record(
            'import.published',
            'api_import_job',
            $id,
            ['status' => 'staged'],
            ['status' => 'published', 'published_drafts' => $published],
            $request
        );

        return $this->show($id);
    }

    /** @return array<string,mixed> */
    public function cancel(string $id, Request $request): array
    {
        $job = $this->findScoped($id);
        if (in_array((string) $job['status'], ['cancelled', 'published'], true)) {
            throw new AdminApiException(409, 'conflict', 'Import job cannot be cancelled in its current status.');
        }

        Database::query(
            'UPDATE api_import_jobs SET status = ?, updated_at = NOW() WHERE id = ?',
            ['cancelled', $id]
        );

        AdminApiAudit::record(
            'import.cancelled',
            'api_import_job',
            $id,
            ['status' => (string) $job['status']],
            ['status' => 'cancelled'],
            $request
        );

        return $this->show($id);
    }

    /** @return array<string,mixed> */
    public function retry(string $id, Request $request): array
    {
        $job = $this->findScoped($id);
        if (!in_array((string) $job['status'], ['failed', 'validated', 'received'], true)) {
            throw new AdminApiException(409, 'conflict', 'Import job cannot be retried in its current status.');
        }

        Database::query(
            'UPDATE api_import_job_items SET status = ?, error_json = NULL WHERE job_id = ? AND status IN (?, ?)',
            ['pending', $id, 'invalid', 'failed']
        );

        Database::query(
            'UPDATE api_import_jobs SET status = ?, updated_at = NOW() WHERE id = ?',
            ['received', $id]
        );

        AdminApiAudit::record(
            'import.retried',
            'api_import_job',
            $id,
            ['status' => (string) $job['status']],
            ['status' => 'received'],
            $request
        );

        return $this->validate($id, $request);
    }

    /** @return array<string,mixed> */
    private function findScoped(string $id): array
    {
        $row = Database::selectOne(
            'SELECT * FROM api_import_jobs WHERE id = ? AND brand_id = ?',
            [$id, AdminApiBrandScope::brandId()]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Import job not found.');
        }

        return $row;
    }

    /**
     * @param array<string,mixed> $job
     * @param list<array<string,mixed>> $items
     * @return array<string,mixed>
     */
    private function detail(array $job, array $items): array
    {
        return [
            'id' => (string) $job['id'],
            'status' => (string) $job['status'],
            'package_checksum' => (string) $job['package_checksum'],
            'item_count' => (int) ($job['item_count'] ?? 0),
            'meta' => $this->decodeJsonField($job['meta_json'] ?? null),
            'error' => $this->decodeJsonField($job['error_json'] ?? null),
            'created_at' => (string) ($job['created_at'] ?? ''),
            'updated_at' => $job['updated_at'] ?? null,
            'items' => array_map(fn (array $item): array => [
                'line_no' => (int) $item['line_no'],
                'entity_type' => (string) $item['entity_type'],
                'status' => (string) $item['status'],
                'payload' => $this->decodeJsonField($item['payload_json']),
                'error' => $item['error_json'] !== null ? $this->decodeJsonField($item['error_json']) : null,
                'draft_id' => $item['draft_id'] ?? null,
            ], $items),
        ];
    }

    /** @return array<string,mixed> */
    private function decodeJsonField(mixed $value): array
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

    private static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
