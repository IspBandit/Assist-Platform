<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Request;

/**
 * Read-only ops failure surfaces for Assist RIC Operations (Increment I).
 *
 * Covers failed email_queue rows and failed scheduled_tasks. Not a Laravel-style
 * failed_jobs table. Bodies/secrets are never returned.
 */
final class AdminApiOpsService
{
    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    public function listFailedEmails(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('email_queue');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'email_queue_missing', 'failed_emails');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ["status = 'failed'"];
        $params = [];
        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, template_key, recipient_email, recipient_name, subject, status, attempts, '
            . 'last_attempt_at, last_error, scheduled_at, sent_at, created_at '
            . 'FROM email_queue WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => $this->emailSummary($row), $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'queue' => 'failed_emails',
                'writable' => false,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    public function listFailedScheduledTasks(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));

        try {
            $tableReady = Database::tableExists('scheduled_tasks');
        } catch (\Throwable) {
            $tableReady = false;
        }

        if (!$tableReady) {
            return $this->emptyPage($limit, 'scheduled_tasks_missing', 'failed_scheduled_tasks');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ["last_status = 'failed'"];
        $params = [];
        if ($afterId !== null) {
            $where[] = 'id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT id, task_key, description, last_run_at, last_status, last_message, last_duration_ms '
            . 'FROM scheduled_tasks WHERE ' . implode(' AND ', $where)
            . ' ORDER BY id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(fn (array $row): array => $this->taskSummary($row), $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'queue' => 'failed_scheduled_tasks',
                'writable' => false,
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function emailSummary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'template_key' => $row['template_key'] !== null ? (string) $row['template_key'] : null,
            'recipient_email' => (string) ($row['recipient_email'] ?? ''),
            'recipient_name' => $row['recipient_name'] !== null ? (string) $row['recipient_name'] : null,
            'subject' => (string) ($row['subject'] ?? ''),
            'status' => (string) ($row['status'] ?? ''),
            'attempts' => (int) ($row['attempts'] ?? 0),
            'last_attempt_at' => $row['last_attempt_at'] ?? null,
            'last_error' => $row['last_error'] !== null ? (string) $row['last_error'] : null,
            'scheduled_at' => $row['scheduled_at'] ?? null,
            'sent_at' => $row['sent_at'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function taskSummary(array $row): array
    {
        return [
            'id' => (string) $row['id'],
            'task_key' => (string) ($row['task_key'] ?? ''),
            'description' => $row['description'] !== null ? (string) $row['description'] : null,
            'last_run_at' => $row['last_run_at'] ?? null,
            'last_status' => (string) ($row['last_status'] ?? ''),
            'last_message' => $row['last_message'] !== null ? (string) $row['last_message'] : null,
            'last_duration_ms' => isset($row['last_duration_ms']) && $row['last_duration_ms'] !== null
                ? (int) $row['last_duration_ms']
                : null,
        ];
    }

    /**
     * @return array{items:list<array<string,mixed>>,meta:array<string,mixed>,links:array<string,mixed>}
     */
    private function emptyPage(int $limit, string $source, string $queue): array
    {
        return [
            'items' => [],
            'meta' => [
                'count' => 0,
                'limit' => $limit,
                'has_more' => false,
                'next_cursor' => null,
                'sparse' => true,
                'source' => $source,
                'queue' => $queue,
                'writable' => false,
            ],
            'links' => ['next' => null],
        ];
    }
}
