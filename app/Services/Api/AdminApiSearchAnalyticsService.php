<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\Demand\ReportingService;

/**
 * Search analytics collections for Admin API (Option B Increment E).
 */
final class AdminApiSearchAnalyticsService
{
    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function searches(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));
        [$from, $to] = $this->dateRange($request);
        $brandId = AdminApiBrandScope::brandId();

        if (!Database::tableExists('provider_searches')) {
            return $this->emptyCollection($limit, $from, $to, 'provider_searches_missing');
        }

        $afterId = AdminApiCursor::decode($request->query('cursor'));
        $where = ['s.is_excluded = 0', 's.created_at BETWEEN ? AND ?', '(s.brand_id = ? OR s.brand_id IS NULL)'];
        $params = [$from . ' 00:00:00', $to . ' 23:59:59', $brandId];

        if ($afterId !== null) {
            $where[] = 's.id < ?';
            $params[] = $afterId;
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT s.id, s.town_id, s.category_id, s.state_id, s.result_count, s.urgency, s.created_at, '
            . 'COALESCE(c.name, NULL) AS category_name, COALESCE(t.name, NULL) AS town_name, COALESCE(st.abbreviation, \'\') AS state_abbr '
            . 'FROM provider_searches s '
            . 'LEFT JOIN towns t ON t.id = s.town_id '
            . 'LEFT JOIN states st ON st.id = s.state_id '
            . 'LEFT JOIN service_categories c ON c.id = s.category_id '
            . 'WHERE ' . implode(' AND ', $where) . ' ORDER BY s.id DESC LIMIT ' . $fetchLimit,
            $params
        );

        $page = AdminApiCursor::page($rows, $limit, static fn (array $row): int => (int) $row['id']);

        return [
            'items' => array_map(static fn (array $row): array => [
                'id' => (string) $row['id'],
                'town_id' => $row['town_id'] !== null ? (int) $row['town_id'] : null,
                'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
                'category_name' => $row['category_name'] !== null ? (string) $row['category_name'] : null,
                'town_name' => $row['town_name'] !== null ? (string) $row['town_name'] : null,
                'state' => $row['state_abbr'] !== null ? (string) $row['state_abbr'] : null,
                'result_count' => (int) ($row['result_count'] ?? 0),
                'urgency' => $row['urgency'] !== null ? (string) $row['urgency'] : null,
                'created_at' => (string) ($row['created_at'] ?? ''),
            ], $page['items']),
            'meta' => $this->meta($page, $limit, $from, $to, $page['items'] === []),
            'links' => ['next' => $page['next_cursor']],
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function searchIntents(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));
        [$from, $to] = $this->dateRange($request);
        $brandId = AdminApiBrandScope::brandId();

        if (!Database::tableExists('provider_searches')) {
            return $this->emptyCollection($limit, $from, $to, 'provider_searches_missing');
        }

        $offset = $this->decodeOffset($request->query('cursor'));
        $fetchLimit = $limit + 1;

        $rows = Database::select(
            'SELECT s.category_id, COALESCE(c.name, \'Any category\') AS category_name, COALESCE(c.slug, NULL) AS category_slug, '
            . 'COUNT(*) AS search_count, AVG(s.result_count) AS avg_results '
            . 'FROM provider_searches s '
            . 'LEFT JOIN service_categories c ON c.id = s.category_id '
            . 'WHERE s.is_excluded = 0 AND s.created_at BETWEEN ? AND ? AND (s.brand_id = ? OR s.brand_id IS NULL) '
            . 'GROUP BY s.category_id, c.name, c.slug '
            . 'ORDER BY search_count DESC LIMIT ' . ($fetchLimit + $offset) . ' OFFSET ' . $offset,
            [$from . ' 00:00:00', $to . ' 23:59:59', $brandId]
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(static fn (array $row): array => [
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
            'category_name' => (string) $row['category_name'],
            'intent' => $row['category_slug'] !== null ? (string) $row['category_slug'] : null,
            'search_count' => (int) ($row['search_count'] ?? 0),
            'avg_result_count' => round((float) ($row['avg_results'] ?? 0), 2),
        ], $rows);

        $nextCursor = $hasMore ? $this->encodeOffset($offset + count($items)) : null;

        return [
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'from' => $from,
                'to' => $to,
                'brand_id' => $brandId,
                'sparse' => $items === [],
            ],
            'links' => ['next' => $nextCursor],
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    public function searchResultsPerformance(Request $request): array
    {
        $limit = AdminApiCursor::limit($request->query('limit'));
        [$from, $to] = $this->dateRange($request);
        $brandId = AdminApiBrandScope::brandId();

        if (!Database::tableExists('provider_search_results') || !Database::tableExists('provider_searches')) {
            return $this->emptyCollection($limit, $from, $to, 'provider_search_results_missing');
        }

        $offset = $this->decodeOffset($request->query('cursor'));
        $fetchLimit = $limit + 1;

        $rows = Database::select(
            'SELECT r.provider_id, p.business_name, COUNT(*) AS impression_count, AVG(r.rank_position) AS avg_rank '
            . 'FROM provider_search_results r '
            . 'INNER JOIN provider_searches s ON s.id = r.search_id '
            . 'INNER JOIN providers p ON p.id = r.provider_id '
            . 'WHERE s.is_excluded = 0 AND s.created_at BETWEEN ? AND ? AND (s.brand_id = ? OR s.brand_id IS NULL) '
            . 'GROUP BY r.provider_id, p.business_name '
            . 'ORDER BY impression_count DESC LIMIT ' . ($fetchLimit + $offset) . ' OFFSET ' . $offset,
            [$from . ' 00:00:00', $to . ' 23:59:59', $brandId]
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(static fn (array $row): array => [
            'provider_id' => (string) $row['provider_id'],
            'provider_name' => (string) ($row['business_name'] ?? ''),
            'impression_count' => (int) ($row['impression_count'] ?? 0),
            'avg_rank' => round((float) ($row['avg_rank'] ?? 0), 2),
        ], $rows);

        $nextCursor = $hasMore ? $this->encodeOffset($offset + count($items)) : null;

        return [
            'items' => $items,
            'meta' => [
                'count' => count($items),
                'limit' => $limit,
                'has_more' => $hasMore,
                'next_cursor' => $nextCursor,
                'from' => $from,
                'to' => $to,
                'brand_id' => $brandId,
                'sparse' => $items === [],
            ],
            'links' => ['next' => $nextCursor],
        ];
    }

    /**
     * @param array{count:int,has_more:bool,next_cursor:?string} $page
     * @return array<string,mixed>
     */
    private function meta(array $page, int $limit, string $from, string $to, bool $sparse): array
    {
        return [
            'count' => $page['count'],
            'limit' => $limit,
            'has_more' => $page['has_more'],
            'next_cursor' => $page['next_cursor'],
            'from' => $from,
            'to' => $to,
            'brand_id' => AdminApiBrandScope::brandId(),
            'sparse' => $sparse,
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

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    private function emptyCollection(int $limit, string $from, string $to, string $source): array
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
                'brand_id' => AdminApiBrandScope::brandId(),
                'sparse' => true,
                'source' => $source,
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

    private function decodeOffset(mixed $cursor): int
    {
        $cursor = trim((string) $cursor);
        if ($cursor === '') {
            return 0;
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
        if (!is_array($payload) || !isset($payload['offset'])) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        return max(0, (int) $payload['offset']);
    }

    private function encodeOffset(int $offset): string
    {
        $json = json_encode(['offset' => $offset], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
