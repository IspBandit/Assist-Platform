<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Platform\AiSearch\Knowledge\KnowledgeGapService;
use App\Platform\AiSearch\Knowledge\SearchGapDualSource;
use App\Services\Demand\ReportingService;

/**
 * Aggregated zero-result search gaps for RIC research workbench (CORE-011 Increment 8).
 *
 * Dual-source Option B: unions `provider_searches` zeros with `knowledge_gaps`
 * (when the table and KnowledgeGapService are present) via SearchGapDualSource.
 * Collection meta.source is "dual"; each item carries meta.source.
 *
 * @see docs/SEARCH_GAP_DUAL_SOURCE.md (AI branch) / OpenAPI /search-gaps description
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
        $brandKey = AdminApiBrandScope::brand()->id();

        // Over-fetch provider rows so merge is not starved; cursor stays provider-primary.
        $providerFetch = min(500, max($limit + 1, $limit * 2));
        $providerItems = [];
        $hasMore = false;
        $nextCursor = null;

        if (Database::tableExists('provider_searches')) {
            $where = ['s.is_excluded = 0', 's.result_count = 0', 's.created_at BETWEEN ? AND ?'];
            $params = [$from . ' 00:00:00', $to . ' 23:59:59'];

            $where[] = '(s.brand_id = ? OR s.brand_id IS NULL)';
            $params[] = $brandId;

            if ($search !== '') {
                $like = '%' . $search . '%';
                $where[] = '(COALESCE(c.name, \'\') LIKE ? OR COALESCE(t.name, \'\') LIKE ? OR COALESCE(st.abbreviation, \'\') LIKE ?)';
                array_push($params, $like, $like, $like);
            }

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
                . 'LIMIT ' . $providerFetch . ' OFFSET ' . $offset,
                $params
            );

            // Provider-primary pagination: more than one page of provider aggregates remains.
            $hasMore = count($rows) > $limit;
            $providerItems = array_map(function (array $row): array {
                $item = $this->mapGap($row);
                $item['meta'] = ['source' => SearchGapDualSource::SOURCE_PROVIDER];

                return $item;
            }, $rows);
            $nextCursor = $hasMore ? $this->encodeOffset($offset + $limit) : null;
        }

        $knowledgeItems = $this->loadKnowledgeGapItems($brandKey, $providerFetch, $from, $to);
        $dual = new SearchGapDualSource();
        $merged = $dual->merge($providerItems, $knowledgeItems, $limit, [
            'from' => $from,
            'to' => $to,
            'brand_id' => $brandId,
            'brand_key' => $brandKey,
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ]);

        return [
            'items' => $merged['items'],
            'meta' => $merged['meta'],
            'links' => [
                'next' => $nextCursor,
            ],
        ];
    }

    /**
     * Load open knowledge gaps when the knowledge_gaps table exists.
     *
     * @return list<array<string,mixed>>
     */
    private function loadKnowledgeGapItems(string $brandKey, int $fetchLimit, string $from, string $to): array
    {
        if (!Database::tableExists('knowledge_gaps')) {
            return [];
        }

        try {
            $gapSvc = new KnowledgeGapService();
            $rows = $gapSvc->listForAdmin($brandKey, KnowledgeGapService::STATUS_OPEN, $fetchLimit);
            $items = $gapSvc->toSearchGapItems($rows);

            return (new SearchGapDualSource())->filterByDateWindow($items, $from, $to);
        } catch (\Throwable) {
            return [];
        }
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
