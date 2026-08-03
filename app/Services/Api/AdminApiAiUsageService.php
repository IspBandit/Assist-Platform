<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\Demand\ReportingService;

/**
 * AI usage, cost and cache performance summaries (Option B Increment E).
 */
final class AdminApiAiUsageService
{
    /** @return array<string,mixed> */
    public function summary(Request $request): array
    {
        [$from, $to] = $this->dateRange($request);
        $brandKey = AdminApiBrandScope::brand()->id();

        if (!Database::tableExists('ai_usage_daily')) {
            return $this->emptySummary($from, $to, $brandKey);
        }

        $row = Database::selectOne(
            'SELECT COALESCE(SUM(requests), 0) AS requests, COALESCE(SUM(input_tokens), 0) AS input_tokens, '
            . 'COALESCE(SUM(output_tokens), 0) AS output_tokens, COALESCE(SUM(estimated_cost_aud), 0) AS estimated_cost_aud, '
            . 'COALESCE(SUM(cache_hits), 0) AS cache_hits '
            . 'FROM ai_usage_daily WHERE usage_date BETWEEN ? AND ? AND (brand_key = ? OR brand_key = \'\')',
            [$from, $to, $brandKey]
        ) ?? [];

        $settings = $this->settings();

        return [
            'from' => $from,
            'to' => $to,
            'brand_key' => $brandKey,
            'requests' => (int) ($row['requests'] ?? 0),
            'input_tokens' => (int) ($row['input_tokens'] ?? 0),
            'output_tokens' => (int) ($row['output_tokens'] ?? 0),
            'estimated_cost_aud' => (float) ($row['estimated_cost_aud'] ?? 0),
            'cache_hits' => (int) ($row['cache_hits'] ?? 0),
            'ai_enabled' => (bool) ($settings['ai_enabled'] ?? false),
            'openai_enabled' => (bool) ($settings['openai_enabled'] ?? false),
            'sparse' => ((int) ($row['requests'] ?? 0)) === 0,
        ];
    }

    /** @return array<string,mixed> */
    public function costs(Request $request): array
    {
        [$from, $to] = $this->dateRange($request);
        $brandKey = AdminApiBrandScope::brand()->id();

        if (!Database::tableExists('ai_usage_daily')) {
            return [
                'from' => $from,
                'to' => $to,
                'brand_key' => $brandKey,
                'daily' => [],
                'total_estimated_cost_aud' => 0.0,
                'sparse' => true,
            ];
        }

        $rows = Database::select(
            'SELECT usage_date, requests, estimated_cost_aud, cache_hits '
            . 'FROM ai_usage_daily WHERE usage_date BETWEEN ? AND ? AND (brand_key = ? OR brand_key = \'\') '
            . 'ORDER BY usage_date ASC',
            [$from, $to, $brandKey]
        );

        $total = 0.0;
        $daily = [];
        foreach ($rows as $row) {
            $cost = (float) ($row['estimated_cost_aud'] ?? 0);
            $total += $cost;
            $daily[] = [
                'date' => (string) $row['usage_date'],
                'requests' => (int) ($row['requests'] ?? 0),
                'estimated_cost_aud' => $cost,
                'cache_hits' => (int) ($row['cache_hits'] ?? 0),
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'brand_key' => $brandKey,
            'daily' => $daily,
            'total_estimated_cost_aud' => round($total, 4),
            'sparse' => $daily === [],
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function requests(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));
        [$from, $to] = $this->dateRange($request);
        $brandKey = AdminApiBrandScope::brand()->id();

        if (!Database::tableExists('ai_usage_events')) {
            return $this->emptyCollection($limit, $from, $to);
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['e.created_at BETWEEN ? AND ?', '(e.brand_key = ? OR e.brand_key = \'\')'];
        $params = [$from . ' 00:00:00', $to . ' 23:59:59', $brandKey];

        if ($afterId !== null) {
            $where[] = 'e.id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT e.id, e.request_id, e.brand_key, e.operation_type, e.provider, e.model, '
            . 'e.input_tokens, e.output_tokens, e.cached, e.estimated_cost_aud, e.success, e.created_at, '
            . 's.raw_query, s.normalised_query, s.intent_json, s.intent_source, s.confidence, '
            . 's.local_result_count, s.external_result_count, s.fallback_reason, s.response_summary '
            . 'FROM ai_usage_events e LEFT JOIN assist_searches s ON s.request_id = e.request_id '
            . 'WHERE ' . implode(' AND ', $where)
            . ' ORDER BY e.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'request_id' => $row['request_id'] !== null ? (string) $row['request_id'] : null,
                'operation_type' => (string) $row['operation_type'],
                'provider' => $row['provider'] !== null ? (string) $row['provider'] : null,
                'model' => $row['model'] !== null ? (string) $row['model'] : null,
                'input_tokens' => (int) ($row['input_tokens'] ?? 0),
                'output_tokens' => (int) ($row['output_tokens'] ?? 0),
                'cached' => (bool) ((int) ($row['cached'] ?? 0)),
                'estimated_cost_aud' => (float) ($row['estimated_cost_aud'] ?? 0),
                'success' => (bool) ((int) ($row['success'] ?? 1)),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'question' => $row['raw_query'] !== null ? (string) $row['raw_query'] : null,
                'normalised_question' => $row['normalised_query'] !== null ? (string) $row['normalised_query'] : null,
                'interpretation' => self::decodeJson($row['intent_json'] ?? null),
                'intent_source' => $row['intent_source'] !== null ? (string) $row['intent_source'] : null,
                'confidence' => $row['confidence'] !== null ? (float) $row['confidence'] : null,
                'local_result_count' => (int) ($row['local_result_count'] ?? 0),
                'external_result_count' => (int) ($row['external_result_count'] ?? 0),
                'fallback_reason' => $row['fallback_reason'] !== null ? (string) $row['fallback_reason'] : null,
                'answer_summary' => self::decodeJson($row['response_summary'] ?? null),
            ], $page['items']),
            'meta' => [
                'count' => $page['count'],
                'limit' => $limit,
                'has_more' => $page['has_more'],
                'next_cursor' => $page['next_cursor'],
                'from' => $from,
                'to' => $to,
                'brand_key' => $brandKey,
                'sparse' => $page['items'] === [],
            ],
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /** @return array<string,mixed> */
    public function cachePerformance(Request $request): array
    {
        [$from, $to] = $this->dateRange($request);
        $brandKey = AdminApiBrandScope::brand()->id();

        if (!Database::tableExists('ai_usage_daily')) {
            return [
                'from' => $from,
                'to' => $to,
                'brand_key' => $brandKey,
                'requests' => 0,
                'cache_hits' => 0,
                'cache_hit_rate' => 0.0,
                'sparse' => true,
            ];
        }

        $row = Database::selectOne(
            'SELECT COALESCE(SUM(requests), 0) AS requests, COALESCE(SUM(cache_hits), 0) AS cache_hits '
            . 'FROM ai_usage_daily WHERE usage_date BETWEEN ? AND ? AND (brand_key = ? OR brand_key = \'\')',
            [$from, $to, $brandKey]
        ) ?? [];

        $requests = (int) ($row['requests'] ?? 0);
        $cacheHits = (int) ($row['cache_hits'] ?? 0);

        return [
            'from' => $from,
            'to' => $to,
            'brand_key' => $brandKey,
            'requests' => $requests,
            'cache_hits' => $cacheHits,
            'cache_hit_rate' => $requests > 0 ? round($cacheHits / $requests, 4) : 0.0,
            'sparse' => $requests === 0,
        ];
    }

    /** @return array{0:string,1:string} */
    private function dateRange(Request $request): array
    {
        $from = $this->parseDate($request->query('from'), 'from');
        $to = $this->parseDate($request->query('to'), 'to');
        if ($from === null && $to === null) {
            return ReportingService::resolveRange('30d');
        }
        if ($from === null) {
            $from = (new \DateTimeImmutable($to))->modify('-29 days')->format('Y-m-d');
        }
        if ($to === null) {
            $to = (new \DateTimeImmutable('today'))->format('Y-m-d');
        }
        if ($from > $to) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['from' => ['From date must be on or before to date.']]
            );
        }

        return [$from, $to];
    }

    /** @return array<string,mixed> */
    private function settings(): array
    {
        if (!Database::tableExists('ai_settings')) {
            return ['ai_enabled' => false, 'openai_enabled' => false];
        }

        return Database::selectOne('SELECT ai_enabled, openai_enabled FROM ai_settings WHERE id = 1') ?? [];
    }

    /** @return array<string,mixed> */
    private function emptySummary(string $from, string $to, string $brandKey): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'brand_key' => $brandKey,
            'requests' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'estimated_cost_aud' => 0.0,
            'cache_hits' => 0,
            'ai_enabled' => false,
            'openai_enabled' => false,
            'sparse' => true,
            'source' => 'ai_usage_missing',
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    private function emptyCollection(int $limit, string $from, string $to): array
    {
        return [
            'items' => [],
            'meta' => [
                'count' => 0,
                'limit' => $limit,
                'has_more' => false,
                'next_cursor' => null,
                'from' => $from,
                'to' => $to,
                'sparse' => true,
                'source' => 'ai_usage_events_missing',
            ],
            'links' => ['next' => null],
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

    /** @return array<string,mixed>|list<mixed>|null */
    private static function decodeJson(mixed $value): ?array
    {
        if (!is_string($value) || $value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : null;
    }
}
