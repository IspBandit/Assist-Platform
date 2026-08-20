<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Listing correction review queue (Option B Increment B).
 */
final class AdminApiCorrectionService
{
    /** @var list<string> */
    private const STATUSES = ['pending', 'approved', 'rejected'];

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function list(Request $request): array
    {
        if (!Database::tableExists('listing_corrections')) {
            return $this->emptyPage(AdminApiCursor::limit($request->query('limit')));
        }

        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $status = strtolower(trim((string) $request->query('status', '')));
        $entityType = strtolower(trim((string) $request->query('entity_type', '')));

        $where = ['1=1'];
        $params = [];

        if ($status !== '') {
            if (!in_array($status, self::STATUSES, true)) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['status' => ['Status must be pending, approved or rejected.']]
                );
            }
            $where[] = 'status = ?';
            $params[] = $status;
        }

        if ($entityType !== '') {
            $where[] = 'entity_type = ?';
            $params[] = $entityType;
        }

        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT * FROM listing_corrections WHERE ' . implode(' AND ', $where)
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

    /** @return array<string,mixed> */
    public function approve(int $id, Request $request): array
    {
        return $this->review($id, 'approved', $request, null);
    }

    /**
     * @param array<string,mixed> $input
     * @return array<string,mixed>
     */
    public function reject(int $id, array $input, Request $request): array
    {
        $reason = trim((string) ($input['reason'] ?? ''));
        if (strlen($reason) < 3) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['reason' => ['A rejection reason of at least 3 characters is required.']]
            );
        }

        return $this->review($id, 'rejected', $request, $reason);
    }

    /** @return array<string,mixed> */
    private function review(int $id, string $decision, Request $request, ?string $reason): array
    {
        $row = $this->find($id);
        if ((string) $row['status'] !== 'pending') {
            throw new AdminApiException(409, 'conflict', 'Only pending corrections can be reviewed.');
        }

        Database::query(
            'UPDATE listing_corrections SET status = ?, reviewed_by = ?, reviewed_at = NOW(), reason = ?, updated_at = NOW() WHERE id = ?',
            [$decision, AdminApiContext::userId(), $reason, $id]
        );

        AdminApiAudit::record(
            'correction.' . $decision,
            'listing_correction',
            $id,
            ['status' => 'pending'],
            ['status' => $decision, 'reason' => $reason],
            $request
        );

        return $this->detail($this->find($id));
    }

    /** @return array<string,mixed> */
    private function find(int $id): array
    {
        if (!Database::tableExists('listing_corrections')) {
            throw new AdminApiException(404, 'not_found', 'Correction not found.');
        }

        $row = Database::selectOne('SELECT * FROM listing_corrections WHERE id = ?', [$id]);
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Correction not found.');
        }

        return $row;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function summary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'entity_type' => (string) $row['entity_type'],
            'entity_id' => (string) $row['entity_id'],
            'field_name' => (string) $row['field_name'],
            'status' => (string) $row['status'],
            'submitter_email' => (string) $row['submitter_email'],
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function detail(array $row): array
    {
        return array_merge($this->summary($row), [
            'submitter_name' => (string) ($row['submitter_name'] ?? ''),
            'proposed_value' => (string) ($row['proposed_value'] ?? ''),
            'current_value' => $row['current_value'] !== null ? (string) $row['current_value'] : null,
            'reason' => $row['reason'] !== null ? (string) $row['reason'] : null,
            'reviewed_by' => $row['reviewed_by'] !== null ? (int) $row['reviewed_by'] : null,
            'reviewed_at' => $row['reviewed_at'] ?? null,
            'updated_at' => $row['updated_at'] ?? null,
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
                'source' => 'listing_corrections_missing',
            ],
            'links' => ['next' => null],
        ];
    }
}
