<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\ProviderClaimService;

/**
 * Duplicate suspect review and safe merge workflow (Option B Increment C).
 */
final class AdminApiDuplicateService
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
        $limit = AdminApiCursor::limit($request->query('limit'));
        $status = strtolower(trim((string) $request->query('status', 'open')));
        $includeSuspects = self::boolish($request->query('include_suspects', 'true'));
        $brandId = AdminApiBrandScope::brandId();

        $items = [];

        if (Database::tableExists('api_duplicate_decisions')) {
            $where = ['brand_id = ?'];
            $params = [$brandId];

            if ($status !== '') {
                $where[] = 'status = ?';
                $params[] = $status;
            }

            $rows = Database::select(
                'SELECT * FROM api_duplicate_decisions WHERE ' . implode(' AND ', $where)
                . ' ORDER BY created_at DESC LIMIT ' . ($limit + 1),
                $params
            );

            foreach ($rows as $row) {
                $items[] = $this->mapDecision($row);
            }
        }

        if ($includeSuspects && ($status === '' || $status === 'open')) {
            foreach ($this->lazySuspects($limit) as $suspect) {
                if (!$this->pairHasDecision($suspect['record_a_id'], $suspect['record_b_id'], $brandId)) {
                    $items[] = $suspect;
                }
            }
        }

        $page = AdminApiCursor::page($items, $limit, static fn (array $row): int => (int) ($row['_sort'] ?? 0));

        $mapped = array_map(static function (array $row): array {
            unset($row['_sort']);

            return $row;
        }, $page['items']);

        return [
            'items' => $mapped,
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
    public function show(string $id): array
    {
        $row = Database::selectOne(
            'SELECT * FROM api_duplicate_decisions WHERE id = ? AND brand_id = ?',
            [$id, AdminApiBrandScope::brandId()]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        return $this->mapDecision($row);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function check(array $input, Request $request): array
    {
        $recordA = (int) ($input['record_a_id'] ?? 0);
        $recordB = (int) ($input['record_b_id'] ?? 0);
        if ($recordA < 1 || $recordB < 1 || $recordA === $recordB) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['record_a_id' => ['Two distinct provider ids are required.']]
            );
        }

        if ($recordA > $recordB) {
            [$recordA, $recordB] = [$recordB, $recordA];
        }

        $brandId = AdminApiBrandScope::brandId();
        $existing = Database::selectOne(
            'SELECT * FROM api_duplicate_decisions WHERE brand_id = ? AND entity_type = ? AND record_a_id = ? AND record_b_id = ?',
            [$brandId, 'provider', $recordA, $recordB]
        );

        if ($existing !== null) {
            return $this->mapDecision($existing);
        }

        $score = $this->scorePair($recordA, $recordB);
        $id = AdminApiToken::uuid();
        $now = date('Y-m-d H:i:s');

        Database::query(
            'INSERT INTO api_duplicate_decisions (id, entity_type, record_a_id, record_b_id, score, classification, status, reasons_json, brand_id, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $id,
                'provider',
                $recordA,
                $recordB,
                $score['score'],
                $score['classification'],
                'open',
                json_encode($score['reasons'], JSON_THROW_ON_ERROR),
                $brandId,
                $now,
                $now,
            ]
        );

        AdminApiAudit::record(
            'duplicate.checked',
            'api_duplicate_decision',
            $id,
            null,
            ['record_a_id' => $recordA, 'record_b_id' => $recordB, 'score' => $score['score']],
            $request
        );

        return $this->show($id);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function merge(string $id, array $input, Request $request): array
    {
        $decision = $this->findOpenDecision($id);
        $survivingId = (int) ($input['surviving_id'] ?? $decision['record_a_id']);
        $absorbedId = (int) ($input['absorbed_id'] ?? ($survivingId === (int) $decision['record_a_id'] ? (int) $decision['record_b_id'] : (int) $decision['record_a_id']));
        $dryRun = self::boolish($input['dry_run'] ?? false);

        if (!in_array($survivingId, [(int) $decision['record_a_id'], (int) $decision['record_b_id']], true)) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['surviving_id' => ['Surviving id must be one of the decision pair.']]
            );
        }

        if (!in_array($absorbedId, [(int) $decision['record_a_id'], (int) $decision['record_b_id']], true) || $absorbedId === $survivingId) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['absorbed_id' => ['Absorbed id must be the other provider in the pair.']]
            );
        }

        $preview = [
            'decision_id' => $id,
            'surviving_id' => (string) $survivingId,
            'absorbed_id' => (string) $absorbedId,
            'dry_run' => $dryRun,
            'actions' => [
                'soft_delete_absorbed_provider' => true,
                'record_merge_history' => true,
                'close_decision' => true,
            ],
        ];

        if ($dryRun) {
            return $preview;
        }

        $now = date('Y-m-d H:i:s');
        Database::beginTransaction();
        try {
            Database::query(
                'UPDATE providers SET deleted_at = ?, updated_at = NOW() WHERE id = ? AND deleted_at IS NULL',
                [$now, $absorbedId]
            );

            Database::query(
                'UPDATE api_duplicate_decisions SET status = ?, merged_into_id = ?, decided_by = ?, decided_at = ?, updated_at = NOW() WHERE id = ?',
                ['merged', $survivingId, AdminApiContext::userId(), $now, $id]
            );

            Database::query(
                'INSERT INTO api_merge_history (decision_id, surviving_id, absorbed_id, field_choices_json, actor_user_id, actor_client_id, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $id,
                    $survivingId,
                    $absorbedId,
                    json_encode($input['field_choices'] ?? null, JSON_THROW_ON_ERROR),
                    AdminApiContext::userId(),
                    AdminApiContext::clientId(),
                    $now,
                ]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }

        AdminApiAudit::record(
            'duplicate.merged',
            'provider',
            $survivingId,
            ['absorbed_id' => $absorbedId],
            ['decision_id' => $id],
            $request
        );

        return array_merge($preview, ['status' => 'merged', 'dry_run' => false]);
    }

    /** @return array<string,mixed> */
    public function markNotDuplicate(string $id, Request $request): array
    {
        return $this->closeDecision($id, 'not_duplicate', $request);
    }

    /** @return array<string,mixed> */
    public function defer(string $id, Request $request): array
    {
        return $this->closeDecision($id, 'deferred', $request);
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function mergeHistory(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));

        $where = ['d.brand_id = ?'];
        $params = [AdminApiBrandScope::brandId()];

        if ($afterId !== null) {
            $where[] = 'h.id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT h.*, d.entity_type, d.record_a_id, d.record_b_id '
            . 'FROM api_merge_history h '
            . 'INNER JOIN api_duplicate_decisions d ON d.id = h.decision_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'ORDER BY h.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => [
                'id' => (string) $row['id'],
                'decision_id' => (string) $row['decision_id'],
                'entity_type' => (string) $row['entity_type'],
                'surviving_id' => (string) $row['surviving_id'],
                'absorbed_id' => (string) $row['absorbed_id'],
                'field_choices' => $this->decodeJson($row['field_choices_json'] ?? null),
                'created_at' => (string) $row['created_at'],
            ], $page['items']),
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
    private function closeDecision(string $id, string $status, Request $request): array
    {
        $decision = $this->findOpenDecision($id);
        $now = date('Y-m-d H:i:s');

        Database::query(
            'UPDATE api_duplicate_decisions SET status = ?, decided_by = ?, decided_at = ?, updated_at = NOW() WHERE id = ?',
            [$status, AdminApiContext::userId(), $now, $id]
        );

        AdminApiAudit::record(
            'duplicate.' . $status,
            'api_duplicate_decision',
            $id,
            ['status' => (string) $decision['status']],
            ['status' => $status],
            $request
        );

        return $this->show($id);
    }

    /** @return array<string,mixed> */
    private function findOpenDecision(string $id): array
    {
        if (!Database::tableExists('api_duplicate_decisions')) {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        $row = Database::selectOne(
            'SELECT * FROM api_duplicate_decisions WHERE id = ? AND brand_id = ?',
            [$id, AdminApiBrandScope::brandId()]
        );

        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        if ((string) $row['status'] !== 'open') {
            throw new AdminApiException(409, 'conflict', 'Duplicate decision is not open.');
        }

        return $row;
    }

    /** @return list<array<string,mixed>> */
    private function lazySuspects(int $limit): array
    {
        $suspects = ProviderClaimService::duplicateSuspects(min(200, $limit * 2));
        $mapped = [];

        foreach ($suspects as $row) {
            $recordA = (int) $row['id_a'];
            $recordB = (int) $row['id_b'];
            if ($recordA > $recordB) {
                [$recordA, $recordB] = [$recordB, $recordA];
            }

            $mapped[] = [
                'id' => 'suspect:' . $recordA . ':' . $recordB,
                'entity_type' => 'provider',
                'record_a_id' => (string) $recordA,
                'record_b_id' => (string) $recordB,
                'record_a_name' => (string) ($row['name_a'] ?? ''),
                'record_b_name' => (string) ($row['name_b'] ?? ''),
                'status' => 'open',
                'source' => 'duplicate_suspects',
                'classification' => 'heuristic',
                'score' => null,
                '_sort' => $recordA,
            ];
        }

        return $mapped;
    }

    private function pairHasDecision(string $recordA, string $recordB, int $brandId): bool
    {
        if (!Database::tableExists('api_duplicate_decisions')) {
            return false;
        }

        $a = (int) $recordA;
        $b = (int) $recordB;
        if ($a > $b) {
            [$a, $b] = [$b, $a];
        }

        return Database::selectOne(
            'SELECT id FROM api_duplicate_decisions WHERE brand_id = ? AND entity_type = ? AND record_a_id = ? AND record_b_id = ?',
            [$brandId, 'provider', $a, $b]
        ) !== null;
    }

    /**
     * @return array{score:float,classification:string,reasons:list<string>}
     */
    private function scorePair(int $recordA, int $recordB): array
    {
        $p1 = Database::selectOne('SELECT id, phone, website, business_name, base_town_id FROM providers WHERE id = ?', [$recordA]);
        $p2 = Database::selectOne('SELECT id, phone, website, business_name, base_town_id FROM providers WHERE id = ?', [$recordB]);

        if ($p1 === null || $p2 === null) {
            throw new AdminApiException(404, 'not_found', 'One or both providers were not found.');
        }

        $reasons = [];
        $score = 0.0;

        if ($p1['phone'] !== null && $p1['phone'] !== '' && $p1['phone'] === $p2['phone']) {
            $reasons[] = 'matching_phone';
            $score += 40;
        }
        if ($p1['website'] !== null && $p1['website'] !== '' && $p1['website'] === $p2['website']) {
            $reasons[] = 'matching_website';
            $score += 35;
        }
        if ($p1['business_name'] === $p2['business_name'] && $p1['base_town_id'] !== null && $p1['base_town_id'] === $p2['base_town_id']) {
            $reasons[] = 'matching_name_and_town';
            $score += 25;
        }

        return [
            'score' => min(100.0, $score),
            'classification' => $score >= 70 ? 'likely_duplicate' : 'possible_duplicate',
            'reasons' => $reasons !== [] ? $reasons : ['manual_check'],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapDecision(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'entity_type' => (string) $row['entity_type'],
            'record_a_id' => (string) $row['record_a_id'],
            'record_b_id' => (string) $row['record_b_id'],
            'score' => $row['score'] !== null ? (float) $row['score'] : null,
            'classification' => $row['classification'] !== null ? (string) $row['classification'] : null,
            'status' => (string) $row['status'],
            'reasons' => $this->decodeJson($row['reasons_json'] ?? null),
            'merged_into_id' => $row['merged_into_id'] !== null ? (string) $row['merged_into_id'] : null,
            'decided_at' => $row['decided_at'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
            'source' => 'api_duplicate_decisions',
            '_sort' => crc32((string) $row['id']),
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

    private static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }
}
