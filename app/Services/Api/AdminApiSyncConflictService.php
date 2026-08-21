<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * RIC live sync conflict queue (Option B Increment F).
 */
final class AdminApiSyncConflictService
{
    /** @var list<string> */
    private const RESOLUTIONS = ['resolved_push', 'resolved_pull', 'deferred', 'ignored'];

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function list(Request $request): array
    {
        if (!Database::tableExists('api_sync_conflicts')) {
            return $this->emptyPage(AdminApiCursor::limit($request->query('limit')));
        }

        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = $this->decodeCursor($request->query('cursor'));
        $status = strtolower(trim((string) $request->query('status', '')));

        $where = ['brand_id = ?'];
        $params = [AdminApiBrandScope::brandId()];

        if ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($afterId !== null) {
            $where[] = 'created_at < ? OR (created_at = ? AND id < ?)';
            array_push($params, $afterId['created_at'], $afterId['created_at'], $afterId['id']);
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT * FROM api_sync_conflicts WHERE ' . implode(' AND ', $where)
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
            $nextCursor = $this->encodeCursor((string) $last['id'], (string) $last['created_at']);
        }

        $items = array_map(fn (array $row): array => $this->summary($row), $rows);

        return [
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'brand_id' => AdminApiBrandScope::brandId(),
            ],
            'links' => ['next' => $nextCursor],
        ];
    }

    /** @return array<string,mixed> */
    public function show(string $id): array
    {
        return $this->detail($this->find($id));
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function resolve(string $id, array $input, Request $request): array
    {
        $row = $this->find($id);
        if ((string) $row['status'] !== 'open') {
            throw new AdminApiException(409, 'conflict', 'Sync conflict is not open.');
        }

        $resolution = strtolower(trim((string) ($input['resolution'] ?? '')));
        if (!in_array($resolution, self::RESOLUTIONS, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['resolution' => ['Resolution must be resolved_push, resolved_pull, deferred or ignored.']]
            );
        }

        $now = date('Y-m-d H:i:s');
        $resolutionPayload = is_array($input['resolution_payload'] ?? null) ? $input['resolution_payload'] : [];

        Database::query(
            'UPDATE api_sync_conflicts SET status = ?, resolution_json = ?, resolved_by_user_id = ?, resolved_at = ?, updated_at = ? WHERE id = ?',
            [
                $resolution,
                json_encode($resolutionPayload, JSON_THROW_ON_ERROR),
                AdminApiContext::userId(),
                $now,
                $now,
                $id,
            ]
        );

        AdminApiAudit::record(
            'sync_conflict.resolved',
            'api_sync_conflict',
            $id,
            ['status' => 'open'],
            ['status' => $resolution],
            $request
        );

        return $this->detail($this->find($id));
    }

    /** @return array<string,mixed> */
    private function find(string $id): array
    {
        if (!Database::tableExists('api_sync_conflicts')) {
            throw new AdminApiException(404, 'not_found', 'Sync conflict not found.');
        }

        $row = Database::selectOne(
            'SELECT * FROM api_sync_conflicts WHERE id = ? AND brand_id = ?',
            [$id, AdminApiBrandScope::brandId()]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Sync conflict not found.');
        }

        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'entity_type' => (string) $row['entity_type'],
            'local_ref' => (string) $row['local_ref'],
            'live_id' => $row['live_id'] !== null ? (string) $row['live_id'] : null,
            'status' => (string) $row['status'],
            'conflict_reason' => (string) ($row['conflict_reason'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function detail(array $row): array
    {
        return array_merge($this->summary($row), [
            'local_payload' => $this->decodeJson($row['local_payload_json'] ?? null),
            'live_payload' => $this->decodeJson($row['live_payload_json'] ?? null),
            'resolution' => $this->decodeJson($row['resolution_json'] ?? null),
            'created_by_client_id' => $row['created_by_client_id'] !== null ? (string) $row['created_by_client_id'] : null,
            'resolved_by_user_id' => $row['resolved_by_user_id'] !== null ? (int) $row['resolved_by_user_id'] : null,
            'resolved_at' => $row['resolved_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
        ]);
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

    /** @return array{id:string,created_at:string}|null */
    private function decodeCursor(mixed $cursor): ?array
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
        if (!is_array($payload) || !isset($payload['id'], $payload['created_at'])) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        return ['id' => (string) $payload['id'], 'created_at' => (string) $payload['created_at']];
    }

    private function encodeCursor(string $id, string $createdAt): string
    {
        $json = json_encode(['id' => $id, 'created_at' => $createdAt], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
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
                'source' => 'api_sync_conflicts_missing',
            ],
            'links' => ['next' => null],
        ];
    }
}
