<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;

/**
 * Read-only Admin API access to platform audit logs (CORE-011 Increment 8).
 */
final class AdminApiAuditService
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
        if (!Database::tableExists('audit_logs')) {
            throw new AdminApiException(
                503,
                'api_unavailable',
                'Audit log store is not migrated on this deployment.'
            );
        }

        $limit = AdminApiCursor::limit($request->query('limit'));
        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $filters = $this->parseFilters($request);

        $where = ['1=1'];
        $params = [];

        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        if ($filters['action'] !== '') {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }

        if ($filters['object_type'] !== '') {
            $where[] = 'object_type = ?';
            $params[] = $filters['object_type'];
        }

        if ($filters['object_id'] !== '') {
            $where[] = 'object_id = ?';
            $params[] = $filters['object_id'];
        }

        if ($filters['user_id'] !== null) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }

        if ($filters['from'] !== null) {
            $where[] = 'created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }

        if ($filters['to'] !== null) {
            $where[] = 'created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        if ($filters['q'] !== '') {
            $like = '%' . $filters['q'] . '%';
            $where[] = '(action LIKE ? OR object_type LIKE ? OR object_id LIKE ? OR previous_value LIKE ? OR new_value LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, user_id, action, object_type, object_id, previous_value, new_value, ip_address, user_agent, created_at '
            . 'FROM audit_logs WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);
        $items = array_map(fn (array $row): array => $this->mapRow($row), $page['items']);

        return [
            'items' => $items,
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'filters' => array_filter([
                    'action' => $filters['action'] !== '' ? $filters['action'] : null,
                    'object_type' => $filters['object_type'] !== '' ? $filters['object_type'] : null,
                    'object_id' => $filters['object_id'] !== '' ? $filters['object_id'] : null,
                    'user_id' => $filters['user_id'],
                    'from' => $filters['from'],
                    'to' => $filters['to'],
                    'q' => $filters['q'] !== '' ? $filters['q'] : null,
                ], static fn ($value): bool => $value !== null),
            ],
            'links' => [
                'next' => $page['next_cursor'],
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function show(int $id): array
    {
        if (!Database::tableExists('audit_logs')) {
            throw new AdminApiException(
                503,
                'api_unavailable',
                'Audit log store is not migrated on this deployment.'
            );
        }

        $row = Database::selectOne(
            'SELECT id, user_id, action, object_type, object_id, previous_value, new_value, ip_address, user_agent, created_at '
            . 'FROM audit_logs WHERE id = ? LIMIT 1',
            [$id]
        );
        if ($row === null) {
            throw new AdminApiException(404, 'not_found', 'Audit event not found.');
        }

        return $this->mapRow($row);
    }

    /**
     * @return array{
     *   action:string,
     *   object_type:string,
     *   object_id:string,
     *   user_id:?int,
     *   from:?string,
     *   to:?string,
     *   q:string
     * }
     */
    private function parseFilters(Request $request): array
    {
        $userId = trim((string) $request->query('user_id', ''));
        $parsedUserId = null;
        if ($userId !== '') {
            if (!ctype_digit($userId) || (int) $userId < 1) {
                throw new AdminApiException(
                    422,
                    'validation_failed',
                    'Validation failed.',
                    ['user_id' => ['User id must be a positive integer.']]
                );
            }
            $parsedUserId = (int) $userId;
        }

        $from = $this->parseDate($request->query('from'), 'from');
        $to = $this->parseDate($request->query('to'), 'to');
        if ($from !== null && $to !== null && $from > $to) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['from' => ['From date must be on or before to date.']]
            );
        }

        return [
            'action' => trim((string) $request->query('action', '')),
            'object_type' => trim((string) $request->query('object_type', '')),
            'object_id' => trim((string) $request->query('object_id', '')),
            'user_id' => $parsedUserId,
            'from' => $from,
            'to' => $to,
            'q' => trim((string) $request->query('q', '')),
        ];
    }

    private function parseDate(mixed $value, string $field): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                [$field => ['Date must use YYYY-MM-DD format.']]
            );
        }

        return $value;
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapRow(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'action' => (string) $row['action'],
            'object_type' => $row['object_type'] !== null ? (string) $row['object_type'] : null,
            'object_id' => $row['object_id'] !== null ? (string) $row['object_id'] : null,
            'user_id' => $row['user_id'] !== null ? (int) $row['user_id'] : null,
            'previous_value' => $row['previous_value'] !== null ? (string) $row['previous_value'] : null,
            'new_value' => $row['new_value'] !== null ? (string) $row['new_value'] : null,
            'ip_address' => $row['ip_address'] !== null ? (string) $row['ip_address'] : null,
            'user_agent' => $row['user_agent'] !== null ? (string) $row['user_agent'] : null,
            'created_at' => $row['created_at'] !== null ? (string) $row['created_at'] : null,
        ];
    }
}
