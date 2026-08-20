<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Knowledge;

/**
 * Dual-source SearchGap merge for CORE-011 GET /api/v1/admin/search-gaps (Option B).
 *
 * Pure helper: no Admin API / DB dependencies. After CORE-011 merge,
 * AdminApiSearchGapService stamps provider_searches items, loads knowledge_gaps
 * via KnowledgeGapService::toSearchGapItems, then calls merge().
 *
 * Collection meta.source becomes "dual"; each item carries meta.source.
 * Does not invent a second Admin API path or expand locked OpenAPI schemas.
 *
 * @see docs/SEARCH_GAP_DUAL_SOURCE.md
 */
final class SearchGapDualSource
{
    public const SOURCE_PROVIDER = 'provider_searches';
    public const SOURCE_KNOWLEDGE = 'knowledge_gaps';
    public const SOURCE_DUAL = 'dual';

    /**
     * @param list<array<string,mixed>> $providerItems SearchGap-shaped rows from provider_searches
     * @param list<array<string,mixed>> $knowledgeItems SearchGap-shaped rows from knowledge_gaps
     * @param array<string,mixed> $baseMeta Preserved Admin API meta (from/to/brand_id/limit/cursor fields)
     * @return array{
     *   items:list<array<string,mixed>>,
     *   meta:array<string,mixed>
     * }
     */
    public function merge(
        array $providerItems,
        array $knowledgeItems,
        int $limit = 50,
        array $baseMeta = [],
    ): array {
        $limit = max(1, min(500, $limit));

        $provider = [];
        foreach ($providerItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $provider[] = $this->ensureItemSource($item, self::SOURCE_PROVIDER);
        }

        $knowledge = [];
        foreach ($knowledgeItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            $knowledge[] = $this->ensureItemSource($item, self::SOURCE_KNOWLEDGE);
        }

        $merged = array_merge($provider, $knowledge);
        usort($merged, static function (array $a, array $b): int {
            $ua = (float) ($a['urgency_score'] ?? 0);
            $ub = (float) ($b['urgency_score'] ?? 0);
            if ($ua !== $ub) {
                return $ub <=> $ua;
            }
            $sa = (int) ($a['search_count'] ?? 0);
            $sb = (int) ($b['search_count'] ?? 0);
            if ($sa !== $sb) {
                return $sb <=> $sa;
            }
            return strcmp((string) ($b['last_seen'] ?? ''), (string) ($a['last_seen'] ?? ''));
        });

        $truncated = count($merged) > $limit;
        if ($truncated) {
            $merged = array_slice($merged, 0, $limit);
        }

        $meta = array_merge($baseMeta, [
            'count' => count($merged),
            'limit' => $limit,
            'source' => self::SOURCE_DUAL,
            'sources' => [self::SOURCE_PROVIDER, self::SOURCE_KNOWLEDGE],
            'provider_searches_count' => count($provider),
            'knowledge_gaps_count' => count($knowledge),
            'truncated' => $truncated,
            'sparse' => $merged === [],
            'contract' => 'SearchGapCollectionResponse',
        ]);

        return [
            'items' => array_values($merged),
            'meta' => $meta,
        ];
    }

    /**
     * Optional date-window filter for knowledge-gap SearchGap items (YYYY-MM-DD).
     * Uses last_seen (falls back to first_seen) date prefix.
     *
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    public function filterByDateWindow(array $items, ?string $from, ?string $to): array
    {
        if ($from === null && $to === null) {
            return $items;
        }

        $out = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $raw = (string) ($item['last_seen'] ?? $item['first_seen'] ?? '');
            $day = substr($raw, 0, 10);
            if ($day === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $day)) {
                continue;
            }
            if ($from !== null && $day < $from) {
                continue;
            }
            if ($to !== null && $day > $to) {
                continue;
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $item
     * @return array<string,mixed>
     */
    private function ensureItemSource(array $item, string $source): array
    {
        $meta = isset($item['meta']) && is_array($item['meta']) ? $item['meta'] : [];
        if (!isset($meta['source']) || !is_string($meta['source']) || $meta['source'] === '') {
            $meta['source'] = $source;
        }
        $item['meta'] = $meta;

        return $item;
    }
}
