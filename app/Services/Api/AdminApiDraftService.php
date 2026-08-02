<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Admin API draft submissions from RIC and other clients (CORE-011 Increment 7).
 */
final class AdminApiDraftService
{
    /** @var list<string> */
    private const ENTITY_TYPES = ['provider', 'stay'];

    /** @var list<string> */
    private const WRITABLE_STATUSES = ['draft', 'pending_review', 'cancelled'];

    private AdminApiProviderWriteService $providerWrites;
    private AdminApiStayWriteService $stayWrites;

    public function __construct(
        ?AdminApiProviderWriteService $providerWrites = null,
        ?AdminApiStayWriteService $stayWrites = null
    ) {
        $this->providerWrites = $providerWrites ?? new AdminApiProviderWriteService();
        $this->stayWrites = $stayWrites ?? new AdminApiStayWriteService();
    }

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
        $cursor = $this->decodeDraftCursor($request->query('cursor'));
        $status = strtolower(trim((string) $request->query('status', '')));
        $entityType = strtolower(trim((string) $request->query('entity_type', '')));

        $where = ['brand_id = ?'];
        $params = [$brandId];

        if ($cursor !== null) {
            $where[] = '(created_at < ? OR (created_at = ? AND id < ?))';
            array_push($params, $cursor['created_at'], $cursor['created_at'], $cursor['id']);
        }

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($entityType !== '') {
            if (!in_array($entityType, self::ENTITY_TYPES, true)) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['entity_type' => ['Entity type must be provider or stay.']]
                );
            }
            $where[] = 'entity_type = ?';
            $params[] = $entityType;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, entity_type, status, source_system, source_package_id, checksum, live_entity_id, created_at, updated_at '
            . 'FROM api_drafts WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC, id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if ($hasMore && $rows !== []) {
            $last = $rows[array_key_last($rows)];
            $nextCursor = $this->encodeDraftCursor((string) $last['created_at'], (string) $last['id']);
        }

        $items = array_map(fn (array $row): array => $this->summary($row), $rows);

        return [
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
            ],
            'links' => [
                'next' => $nextCursor,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        return $this->detail($this->findScoped($id));
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function create(array $input, Request $request): array
    {
        $entityType = strtolower(trim((string) ($input['entity_type'] ?? '')));
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['entity_type' => ['Entity type must be provider or stay.']]
            );
        }

        $payload = $this->normalisePayload($input['payload'] ?? $input);
        $this->validatePayload($entityType, $payload);

        $id = AdminApiToken::uuid();
        $now = date('Y-m-d H:i:s');
        $status = strtolower(trim((string) ($input['status'] ?? 'draft')));
        if (!in_array($status, self::WRITABLE_STATUSES, true)) {
            $status = 'draft';
        }

        Database::query(
            'INSERT INTO api_drafts (id, entity_type, status, payload_json, source_system, source_package_id, checksum, '
            . 'brand_id, created_by_user_id, created_by_client_id, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $entityType,
                $status,
                json_encode($payload, JSON_THROW_ON_ERROR),
                trim((string) ($input['source_system'] ?? 'ric')) ?: 'ric',
                $this->nullableString($input['source_package_id'] ?? null),
                $this->nullableString($input['checksum'] ?? null),
                AdminApiBrandScope::brandId(),
                AdminApiContext::userId(),
                AdminApiContext::clientId(),
                $now,
                $now,
            ]
        );

        AdminApiAudit::record(
            'draft.created',
            'api_draft',
            $id,
            null,
            ['entity_type' => $entityType, 'status' => $status],
            $request
        );

        return $this->show($id);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function patch(string $id, array $input, Request $request): array
    {
        $row = $this->findScoped($id);
        if (!in_array((string) $row['status'], self::WRITABLE_STATUSES, true)) {
            throw new AdminApiException(409, 'conflict', 'Draft cannot be modified in its current status.');
        }

        $entityType = (string) $row['entity_type'];
        $payload = $this->normalisePayload($input['payload'] ?? $input);
        if ($payload !== []) {
            $merged = array_merge($this->decodePayload($row), $payload);
            $this->validatePayload($entityType, $merged);
            $payload = $merged;
        } else {
            $payload = $this->decodePayload($row);
        }

        $status = array_key_exists('status', $input)
            ? strtolower(trim((string) $input['status']))
            : (string) $row['status'];
        if (!in_array($status, self::WRITABLE_STATUSES, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['status' => ['Status cannot be changed to the requested value via patch.']]
            );
        }

        Database::query(
            'UPDATE api_drafts SET status = ?, payload_json = ?, updated_at = NOW() WHERE id = ?',
            [$status, json_encode($payload, JSON_THROW_ON_ERROR), $id]
        );

        AdminApiAudit::record(
            'draft.updated',
            'api_draft',
            $id,
            ['status' => (string) $row['status']],
            ['status' => $status],
            $request
        );

        return $this->show($id);
    }

    /** @return array<string,mixed> */
    public function approve(string $id, Request $request): array
    {
        $row = $this->findScoped($id);
        if (!in_array((string) $row['status'], ['draft', 'pending_review'], true)) {
            throw new AdminApiException(409, 'conflict', 'Only draft or pending_review drafts can be approved.');
        }

        $entityType = (string) $row['entity_type'];
        $payload = $this->decodePayload($row);
        $this->validatePayload($entityType, $payload);

        $liveEntityId = isset($row['live_entity_id']) ? (int) $row['live_entity_id'] : 0;
        if ($liveEntityId > 0) {
            $live = match ($entityType) {
                'provider' => $this->providerWrites->patch($liveEntityId, $payload, $request),
                'stay' => $this->stayWrites->patch($liveEntityId, $payload, $request),
            };
            $liveEntityId = (int) ($live['id'] ?? $liveEntityId);
        } else {
            $live = match ($entityType) {
                'provider' => $this->providerWrites->create($payload, $request),
                'stay' => $this->stayWrites->create($payload, $request),
            };
            $liveEntityId = (int) ($live['id'] ?? 0);
        }

        Database::query(
            'UPDATE api_drafts SET status = ?, live_entity_id = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() '
            . 'WHERE id = ?',
            ['approved', $liveEntityId > 0 ? $liveEntityId : null, AdminApiContext::userId(), $id]
        );

        AdminApiAudit::record(
            'draft.approved',
            'api_draft',
            $id,
            ['status' => (string) $row['status']],
            ['status' => 'approved', 'live_entity_id' => $liveEntityId],
            $request
        );

        $detail = $this->show($id);
        $detail['live_entity'] = $live;

        return $detail;
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function reject(string $id, array $input, Request $request): array
    {
        $row = $this->findScoped($id);
        if (!in_array((string) $row['status'], ['draft', 'pending_review'], true)) {
            throw new AdminApiException(409, 'conflict', 'Only draft or pending_review drafts can be rejected.');
        }

        $note = trim((string) ($input['review_note'] ?? $input['reason'] ?? ''));

        Database::query(
            'UPDATE api_drafts SET status = ?, review_note = ?, reviewed_by = ?, reviewed_at = NOW(), updated_at = NOW() '
            . 'WHERE id = ?',
            ['rejected', $note !== '' ? $note : null, AdminApiContext::userId(), $id]
        );

        AdminApiAudit::record(
            'draft.rejected',
            'api_draft',
            $id,
            ['status' => (string) $row['status']],
            ['status' => 'rejected', 'review_note' => $note],
            $request
        );

        return $this->show($id);
    }

    public function createFromImportItem(array $row, string $jobId, Request $request): string
    {
        $entityType = (string) $row['entity_type'];
        $payload = $this->decodeJsonField($row['payload_json']);
        $this->validatePayload($entityType, $payload);

        $id = AdminApiToken::uuid();
        $now = date('Y-m-d H:i:s');

        Database::query(
            'INSERT INTO api_drafts (id, entity_type, status, payload_json, source_system, source_package_id, brand_id, '
            . 'created_by_user_id, created_by_client_id, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                $entityType,
                'pending_review',
                json_encode($payload, JSON_THROW_ON_ERROR),
                'ric',
                $jobId,
                AdminApiBrandScope::brandId(),
                AdminApiContext::userId(),
                AdminApiContext::clientId(),
                $now,
                $now,
            ]
        );

        AdminApiAudit::record(
            'draft.created_from_import',
            'api_draft',
            $id,
            null,
            ['import_job_id' => $jobId, 'entity_type' => $entityType],
            $request
        );

        return $id;
    }

    /** @return array<string,mixed> */
    private function findScoped(string $id): array
    {
        $row = Database::selectOne(
            'SELECT * FROM api_drafts WHERE id = ? AND brand_id = ?',
            [$id, AdminApiBrandScope::brandId()]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Draft not found.');
        }

        return $row;
    }

    /** @param array<string,mixed> $row */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'entity_type' => (string) $row['entity_type'],
            'status' => (string) $row['status'],
            'source_system' => (string) ($row['source_system'] ?? 'ric'),
            'source_package_id' => $row['source_package_id'] ?? null,
            'checksum' => $row['checksum'] ?? null,
            'live_entity_id' => isset($row['live_entity_id']) ? (string) $row['live_entity_id'] : null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'updated_at' => $row['updated_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $row */
    private function detail(array $row): array
    {
        $summary = $this->summary($row);
        $summary['payload'] = $this->decodePayload($row);
        $summary['review_note'] = $row['review_note'] ?? null;
        $summary['reviewed_at'] = $row['reviewed_at'] ?? null;

        return $summary;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decodePayload(array $row): array
    {
        return $this->decodeJsonField($row['payload_json'] ?? '{}');
    }

    /** @return array<string,mixed> */
    private function decodeJsonField(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    /** @return array<string,mixed> */
    private function normalisePayload(mixed $payload): array
    {
        if (!is_array($payload)) {
            return [];
        }

        unset($payload['entity_type'], $payload['status'], $payload['source_system'], $payload['source_package_id'], $payload['checksum']);

        return $payload;
    }

    /** @param array<string,mixed> $payload */
    public function validatePayloadPublic(string $entityType, array $payload): void
    {
        $this->validatePayload($entityType, $payload);
    }

    /** @param array<string,mixed> $payload */
    private function validatePayload(string $entityType, array $payload): void
    {
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['entity_type' => ['Entity type must be provider or stay.']]
            );
        }

        if ($entityType === 'provider') {
            $name = trim((string) ($payload['business_name'] ?? ''));
            if ($name === '') {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['payload.business_name' => ['Business name is required for provider drafts.']]
                );
            }
        }

        if ($entityType === 'stay') {
            AdminApiBrandScope::assertStaysEnabled();
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['payload.name' => ['Name is required for stay drafts.']]
                );
            }
        }
    }

    private function encodeDraftCursor(string $createdAt, string $id): string
    {
        $json = json_encode(['created_at' => $createdAt, 'id' => $id], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return array{created_at:string,id:string}|null */
    private function decodeDraftCursor(mixed $cursor): ?array
    {
        $cursor = trim((string) $cursor);
        if ($cursor === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);
        if ($decoded === false) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        $payload = json_decode($decoded, true);
        if (!is_array($payload) || !isset($payload['created_at'], $payload['id'])) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        return [
            'created_at' => (string) $payload['created_at'],
            'id' => (string) $payload['id'],
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
