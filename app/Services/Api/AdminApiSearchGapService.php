<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Services\Demand\ReportingService;

/**
 * Aggregated zero-result search gaps for RIC research workbench (CORE-011 Increment 8).
 *
 * Reads from existing demand analytics tables (`provider_searches`) when present.
 * Returns an empty collection when analytics is disabled or no rows match.
 */
final class AdminApiSearchGapService
{
    /** @var array<string,int> */
    private const URGENCY_WEIGHT = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'urgent' => 4,
    ];

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
        $from = $this->parseDate($request->query('from'), 'from');
        $to = $this->parseDate($request->query('to'), 'to');
        if ($from === null && $to === null) {
            [$from, $to] = ReportingService::resolveRange('30d');
        } elseif ($from === null) {
            $from = (new \DateTimeImmutable($to))->modify('-29 days')->format('Y-m-d');
        } elseif ($to === null) {
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

        $search = trim((string) $request->query('q', ''));
        $offset = $this->decodeOffset($request->query('cursor'));
        $brandId = AdminApiBrandScope::brandId();

        if (!Database::tableExists('provider_searches')) {
            return $this->emptyResult($limit, $from, $to, 'provider_searches_missing');
        }

        $where = ['s.is_excluded = 0', 's.result_count = 0', 's.created_at BETWEEN ? AND ?'];
        $params = [$from . ' 00:00:00', $to . ' 23:59:59'];

        $where[] = '(s.brand_id = ? OR s.brand_id IS NULL)';
        $params[] = $brandId;

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(COALESCE(c.name, \'\') LIKE ? OR COALESCE(t.name, \'\') LIKE ? OR COALESCE(st.abbreviation, \'\') LIKE ?)';
            array_push($params, $like, $like, $like);
        }

        $fetchLimit = $limit + 1;
        $rows = Database::select(
            'SELECT '
            . 's.town_id, s.category_id, s.state_id, '
            . 'COALESCE(c.name, \'Any category\') AS category_name, '
            . 'COALESCE(c.slug, NULL) AS category_slug, '
            . 'COALESCE(t.name, \'Unknown location\') AS town_name, '
            . 'COALESCE(st.abbreviation, \'\') AS state_abbr, '
            . 'COUNT(*) AS search_count, '
            . 'MIN(s.created_at) AS first_seen, '
            . 'MAX(s.created_at) AS last_seen, '
            . 'MAX(s.urgency) AS peak_urgency '
            . 'FROM provider_searches s '
            . 'LEFT JOIN towns t ON t.id = s.town_id '
            . 'LEFT JOIN states st ON st.id = s.state_id '
            . 'LEFT JOIN service_categories c ON c.id = s.category_id '
            . 'WHERE ' . implode(' AND ', $where) . ' '
            . 'GROUP BY s.town_id, s.category_id, s.state_id, c.name, c.slug, t.name, st.abbreviation '
            . 'ORDER BY search_count DESC, last_seen DESC '
            . 'LIMIT ' . ($fetchLimit + $offset) . ' OFFSET ' . $offset,
            $params
        );

        $hasMore = count($rows) > $limit;
        if ($hasMore) {
            array_pop($rows);
        }

        $items = array_map(fn (array $row): array => $this->mapGap($row), $rows);
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
                'brand_key' => AdminApiBrandScope::brand()->id(),
                'source' => 'provider_searches',
                'sparse' => $items === [],
            ],
            'links' => [
                'next' => $nextCursor,
            ],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function mapGap(array $row): array
    {
        $category = (string) $row['category_name'];
        $town = (string) $row['town_name'];
        $state = trim((string) $row['state_abbr']);
        $location = $state !== '' ? $town . ', ' . $state : $town;
        $searchCount = (int) $row['search_count'];
        $peakUrgency = is_string($row['peak_urgency'] ?? null) ? (string) $row['peak_urgency'] : null;

        return [
            'query_text' => $category . ' in ' . $town,
            'location_text' => $location,
            'result_count' => 0,
            'search_count' => $searchCount,
            'first_seen' => (string) $row['first_seen'],
            'last_seen' => (string) $row['last_seen'],
            'intent' => $row['category_slug'] !== null ? (string) $row['category_slug'] : null,
            'urgency_score' => $this->urgencyScore($searchCount, $peakUrgency),
            'town_id' => $row['town_id'] !== null ? (int) $row['town_id'] : null,
            'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        ];
    }

    private function urgencyScore(int $searchCount, ?string $peakUrgency): float
    {
        $weight = self::URGENCY_WEIGHT[$peakUrgency ?? ''] ?? 1;

        return round($searchCount * (0.5 + ($weight / 4)), 2);
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>,
     *   links:array<string,mixed>
     * }
     */
    private function emptyResult(int $limit, string $from, string $to, string $source): array
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
                'brand_key' => AdminApiBrandScope::brand()->id(),
                'source' => $source,
                'sparse' => true,
            ],
            'links' => [
                'next' => null,
            ],
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
        if (!is_array($payload) || !isset($payload['offset']) || !is_int($payload['offset']) && !ctype_digit((string) $payload['offset'])) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        $offset = (int) $payload['offset'];
        if ($offset < 0) {
            throw new AdminApiException(
                422,
                'validation_failed',
                'Validation failed.',
                ['cursor' => ['Cursor is invalid or malformed.']]
            );
        }

        return $offset;
    }

    private function encodeOffset(int $offset): string
    {
        $json = json_encode(['offset' => $offset], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
