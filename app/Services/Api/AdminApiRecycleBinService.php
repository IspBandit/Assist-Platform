<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Recycle Bin list, restore and permanent purge for providers and stays (CORE-011 Increment 6).
 */
final class AdminApiRecycleBinService
{
    /** @var list<string> */
    private const ENTITY_TYPES = ['provider', 'stay'];

    private AdminApiProviderWriteService $providerWrites;
    private AdminApiStayWriteService $stayWrites;
    private AdminApiProviderService $providerReader;
    private AdminApiStayService $stayReader;

    public function __construct(
        ?AdminApiProviderWriteService $providerWrites = null,
        ?AdminApiStayWriteService $stayWrites = null,
        ?AdminApiProviderService $providerReader = null,
        ?AdminApiStayService $stayReader = null
    ) {
        $this->providerWrites = $providerWrites ?? new AdminApiProviderWriteService();
        $this->stayWrites = $stayWrites ?? new AdminApiStayWriteService();
        $this->providerReader = $providerReader ?? new AdminApiProviderService();
        $this->stayReader = $stayReader ?? new AdminApiStayService();
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
        $limit = AdminApiCursor::limit($request->query('limit'));
        $entityType = strtolower(trim((string) $request->query('entity_type', '')));
        $search = trim((string) $request->query('q', ''));
        $cursor = $this->decodeCursor($request->query('cursor'));

        if ($entityType !== '' && !in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['entity_type' => ['Entity type must be provider or stay.']]
            );
        }

        $parts = [];
        $params = [];

        if ($entityType === '' || $entityType === 'provider') {
            $parts[] = $this->providerRecycleSelect($search, $cursor, $params);
        }

        if (($entityType === '' || $entityType === 'stay') && AdminApiBrandScope::staysEnabled()) {
            $parts[] = $this->stayRecycleSelect($search, $cursor, $params);
        }

        if ($parts === []) {
            return [
                'items' => [],
                'meta' => [
                    'count' => 0,
                    'limit' => $limit,
                    'has_more' => false,
                    'next_cursor' => null,
                ],
                'links' => ['next' => null],
            ];
        }

        $union = implode(' UNION ALL ', $parts);
        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT * FROM (' . $union . ') AS recycle_items '
            . 'ORDER BY deleted_at DESC, entity_type ASC, id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $nextCursor = null;
        if ($hasMore && $rows !== []) {
            $last = $rows[array_key_last($rows)];
            $nextCursor = $this->encodeCursor(
                (string) $last['entity_type'],
                (int) $last['id'],
                (string) $last['deleted_at']
            );
        }

        $items = array_map(fn (array $row): array => $this->summary($row), $rows);

        return [
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'entity_type' => $entityType !== '' ? $entityType : null,
            ],
            'links' => [
                'next' => $nextCursor,
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function show(string $entityType, int $id): array
    {
        $entityType = $this->normaliseEntityType($entityType);
        $row = $this->findDeleted($entityType, $id);

        return $this->detail($entityType, $row);
    }

    /** @return array<string,mixed> */
    public function restore(string $entityType, int $id, Request $request): array
    {
        $entityType = $this->normaliseEntityType($entityType);

        return match ($entityType) {
            'provider' => $this->providerWrites->restore($id, $request),
            'stay' => $this->stayWrites->restore($id, $request),
        };
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function purge(string $entityType, int $id, array $body, Request $request): array
    {
        $entityType = $this->normaliseEntityType($entityType);
        $this->assertConfirmAndReason($body, true);

        $row = $this->findDeleted($entityType, $id);
        $reason = trim((string) $body['reason']);

        return match ($entityType) {
            'provider' => $this->purgeProvider($id, $row, $reason, $request),
            'stay' => $this->purgeStay($id, $row, $reason, $request),
        };
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function bulkRestore(array $body, Request $request): array
    {
        $this->assertBulkConfirm($body, false);
        $items = $this->normaliseBulkItems($body['items'] ?? []);

        $results = [];
        foreach ($items as $item) {
            try {
                $restored = $this->restore((string) $item['entity_type'], (int) $item['id'], $request);
                $results[] = [
                    'entity_type' => $item['entity_type'],
                    'id' => (string) $item['id'],
                    'status' => 'restored',
                    'entity' => $restored,
                ];
            } catch (AdminApiException $e) {
                $results[] = [
                    'entity_type' => $item['entity_type'],
                    'id' => (string) $item['id'],
                    'status' => 'failed',
                    'error' => [
                        'code' => $e->errorCode(),
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        }

        return [
            'action' => 'bulk_restore',
            'count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * @param array<string,mixed> $body
     * @return array<string,mixed>
     */
    public function bulkPurge(array $body, Request $request): array
    {
        $this->assertBulkConfirm($body, true);
        $items = $this->normaliseBulkItems($body['items'] ?? []);
        $reason = trim((string) $body['reason']);

        $results = [];
        foreach ($items as $item) {
            try {
                $purged = $this->purge(
                    (string) $item['entity_type'],
                    (int) $item['id'],
                    ['confirm' => true, 'reason' => $reason],
                    $request
                );
                $results[] = [
                    'entity_type' => $item['entity_type'],
                    'id' => (string) $item['id'],
                    'status' => 'purged',
                    'result' => $purged,
                ];
            } catch (AdminApiException $e) {
                $results[] = [
                    'entity_type' => $item['entity_type'],
                    'id' => (string) $item['id'],
                    'status' => 'failed',
                    'error' => [
                        'code' => $e->errorCode(),
                        'message' => $e->getMessage(),
                    ],
                ];
            }
        }

        return [
            'action' => 'bulk_purge',
            'count' => count($results),
            'results' => $results,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function purgeProvider(int $id, array $row, string $reason, Request $request): array
    {
        $brandId = AdminApiBrandScope::brandId();
        $listingId = (int) ($row['listing_id'] ?? 0);
        $providerId = (int) ($row['provider_id'] ?? $id);

        Database::beginTransaction();
        try {
            if ($listingId > 0) {
                Database::query('DELETE FROM provider_brand_listings WHERE id = ? AND brand_id = ?', [$listingId, $brandId]);
            }

            $remaining = (int) Database::scalar(
                'SELECT COUNT(*) FROM provider_brand_listings WHERE provider_id = ?',
                [$providerId]
            );

            $providerPurged = false;
            if ($remaining === 0) {
                Database::query('DELETE FROM providers WHERE id = ?', [$providerId]);
                $providerPurged = true;
            }

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AdminApiAudit::record(
            'recycle_bin.purged',
            'provider',
            $providerId,
            [
                'listing_id' => $listingId,
                'provider_purged' => $providerPurged,
            ],
            ['reason' => $reason, 'brand_scoped' => true],
            $request
        );

        return [
            'entity_type' => 'provider',
            'id' => (string) $providerId,
            'purged' => true,
            'provider_purged' => $providerPurged,
            'listing_purged' => $listingId > 0,
            'reason' => $reason,
            'dependencies' => [
                'note' => $providerPurged
                    ? 'Provider row permanently deleted; child rows cascade per FK definitions.'
                    : 'Brand listing removed; provider retained for other brand listings.',
            ],
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function purgeStay(int $id, array $row, string $reason, Request $request): array
    {
        $affected = Database::query(
            'DELETE FROM caravan_parks WHERE id = ? AND deleted_at IS NOT NULL',
            [$id]
        )->rowCount();

        if ($affected === 0) {
            throw new AdminApiException(404, 'not_found', 'Stay not found in recycle bin.');
        }

        AdminApiAudit::record(
            'recycle_bin.purged',
            'caravan_park',
            $id,
            ['name' => (string) ($row['name'] ?? '')],
            ['reason' => $reason],
            $request
        );

        return [
            'entity_type' => 'stay',
            'id' => (string) $id,
            'purged' => true,
            'reason' => $reason,
            'dependencies' => [
                'note' => 'Stay row permanently deleted; park child tables cascade per FK definitions.',
            ],
        ];
    }

    /**
     * @param list<mixed> $params
     */
    private function providerRecycleSelect(string $search, ?array $cursor, array &$params): string
    {
        $brandId = AdminApiBrandScope::brandId();
        $where = [
            'pbl.brand_id = ?',
            '(p.deleted_at IS NOT NULL OR pbl.deleted_at IS NOT NULL)',
        ];
        $params[] = $brandId;

        if ($search !== '') {
            $where[] = '(p.business_name LIKE ? OR pbl.display_name LIKE ?)';
            $like = '%' . $search . '%';
            array_push($params, $like, $like);
        }

        if ($cursor !== null) {
            $where[] = '(COALESCE(p.deleted_at, pbl.deleted_at) < ? '
                . 'OR (COALESCE(p.deleted_at, pbl.deleted_at) = ? AND p.id < ?))';
            array_push($params, $cursor['deleted_at'], $cursor['deleted_at'], $cursor['id']);
        }

        return 'SELECT \'provider\' AS entity_type, p.id, p.business_name AS name, p.slug, '
            . 'p.id AS provider_id, pbl.id AS listing_id, NULL AS stay_id, '
            . 'COALESCE(p.deleted_at, pbl.deleted_at) AS deleted_at '
            . 'FROM providers p '
            . 'INNER JOIN provider_brand_listings pbl ON pbl.provider_id = p.id '
            . 'WHERE ' . implode(' AND ', $where);
    }

    /**
     * @param list<mixed> $params
     */
    private function stayRecycleSelect(string $search, ?array $cursor, array &$params): string
    {
        $where = ['cp.deleted_at IS NOT NULL'];

        if ($search !== '') {
            $where[] = 'cp.name LIKE ?';
            $params[] = '%' . $search . '%';
        }

        if ($cursor !== null && ($cursor['entity_type'] ?? '') === 'stay') {
            $where[] = '(cp.deleted_at < ? OR (cp.deleted_at = ? AND cp.id < ?))';
            array_push($params, $cursor['deleted_at'], $cursor['deleted_at'], $cursor['id']);
        } elseif ($cursor !== null) {
            $where[] = 'cp.deleted_at <= ?';
            $params[] = $cursor['deleted_at'];
        }

        return 'SELECT \'stay\' AS entity_type, cp.id, cp.name, cp.slug, '
            . 'NULL AS provider_id, NULL AS listing_id, cp.id AS stay_id, cp.deleted_at AS deleted_at '
            . 'FROM caravan_parks cp WHERE ' . implode(' AND ', $where);
    }

    /** @return array<string,mixed> */
    private function findDeleted(string $entityType, int $id): array
    {
        if ($entityType === 'provider') {
            $brandId = AdminApiBrandScope::brandId();
            $row = Database::selectOne(
                'SELECT p.id AS provider_id, p.business_name, p.slug, p.status, p.deleted_at AS provider_deleted_at, '
                . 'pbl.id AS listing_id, pbl.display_name, pbl.deleted_at AS listing_deleted_at, '
                . 'COALESCE(p.deleted_at, pbl.deleted_at) AS deleted_at '
                . 'FROM providers p '
                . 'INNER JOIN provider_brand_listings pbl ON pbl.provider_id = p.id AND pbl.brand_id = ? '
                . 'WHERE p.id = ? AND (p.deleted_at IS NOT NULL OR pbl.deleted_at IS NOT NULL)',
                [$brandId, $id]
            );
            if ($row === null) {
                throw new AdminApiException(404, 'not_found', 'Provider not found in recycle bin.');
            }

            return $row;
        }

        AdminApiBrandScope::assertStaysEnabled();
        $row = Database::selectOne(
            'SELECT id, name, slug, status, deleted_at FROM caravan_parks WHERE id = ? AND deleted_at IS NOT NULL',
            [$id]
        );
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Stay not found in recycle bin.');
        }

        return $row;
    }

    /** @param array<string,mixed> $row */
    private function summary(array $row): array
    {
        return [
            'entity_type' => (string) $row['entity_type'],
            'id' => (string) $row['id'],
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'deleted_at' => (string) ($row['deleted_at'] ?? ''),
            'lifecycle' => 'deleted',
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function detail(string $entityType, array $row): array
    {
        $summary = $this->summary(array_merge($row, ['entity_type' => $entityType, 'id' => $row['provider_id'] ?? $row['id'] ?? 0]));

        if ($entityType === 'provider') {
            $summary['display_name'] = (string) ($row['display_name'] ?? $row['business_name'] ?? '');
            $summary['status'] = (string) ($row['status'] ?? '');
        } else {
            $summary['status'] = (string) ($row['status'] ?? '');
        }

        return $summary;
    }

    private function normaliseEntityType(string $entityType): string
    {
        $entityType = strtolower(trim($entityType));
        if (!in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['entity_type' => ['Entity type must be provider or stay.']]
            );
        }

        return $entityType;
    }

    /**
     * @param mixed $items
     * @return list<array{entity_type:string,id:int}>
     */
    private function normaliseBulkItems(mixed $items): array
    {
        if (!is_array($items) || $items === []) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['items' => ['At least one item is required.']]
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

        $normalised = [];
        foreach ($items as $index => $item) {
            if (!is_array($item)) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['items.' . $index => ['Each item must be an object with entity_type and id.']]
                );
            }

            $entityType = strtolower(trim((string) ($item['entity_type'] ?? '')));
            $id = (int) ($item['id'] ?? 0);
            if (!in_array($entityType, self::ENTITY_TYPES, true) || $id < 1) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['items.' . $index => ['Each item requires entity_type (provider|stay) and a positive id.']]
                );
            }

            $normalised[] = ['entity_type' => $entityType, 'id' => $id];
        }

        return $normalised;
    }

    /** @param array<string,mixed> $body */
    private function assertConfirmAndReason(array $body, bool $requireReason): void
    {
        if (!AdminApiRecycleBinService::boolish($body['confirm'] ?? false)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['confirm' => ['confirm must be true for destructive actions.']]
            );
        }

        if (!$requireReason) {
            return;
        }

        $reason = trim((string) ($body['reason'] ?? ''));
        if (strlen($reason) < 3) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['reason' => ['A purge reason of at least 3 characters is required.']]
            );
        }
    }

    /** @param array<string,mixed> $body */
    private function assertBulkConfirm(array $body, bool $requireReason): void
    {
        $this->assertConfirmAndReason($body, $requireReason);
    }

    /** @return array{entity_type:string,id:int,deleted_at:string}|null */
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
        if (
            !is_array($payload)
            || !isset($payload['entity_type'], $payload['id'], $payload['deleted_at'])
            || !in_array((string) $payload['entity_type'], self::ENTITY_TYPES, true)
        ) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        return [
            'entity_type' => (string) $payload['entity_type'],
            'id' => (int) $payload['id'],
            'deleted_at' => (string) $payload['deleted_at'],
        ];
    }

    private function encodeCursor(string $entityType, int $id, string $deletedAt): string
    {
        $json = json_encode([
            'entity_type' => $entityType,
            'id' => $id,
            'deleted_at' => $deletedAt,
        ], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    private static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }
}
