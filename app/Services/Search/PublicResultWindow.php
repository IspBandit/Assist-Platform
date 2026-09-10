<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Keeps public result pages compact and bounds paid route-matrix requests.
 */
final class PublicResultWindow
{
    public const DEFAULT_LIMIT = 20;
    public const MAX_LIMIT = 40;

    public static function requested(mixed $raw): int
    {
        return is_numeric($raw) && (int) $raw > self::DEFAULT_LIMIT
            ? self::MAX_LIMIT
            : self::DEFAULT_LIMIT;
    }

    /**
     * Fill the visible window in the supplied group order. Call this before
     * road enrichment so one public search never routes more than its window.
     *
     * @param array<string,list<array<string,mixed>>> $groups
     * @return array{groups:array<string,list<array<string,mixed>>>,total:int,has_more:bool}
     */
    public function apply(array $groups, int $limit): array
    {
        $limit = max(1, min(self::MAX_LIMIT, $limit));
        $total = 0;
        foreach ($groups as $rows) {
            $total += count($rows);
        }

        $remaining = $limit;
        $windowed = [];
        foreach ($groups as $key => $rows) {
            $take = max(0, min($remaining, count($rows)));
            $windowed[$key] = $take > 0 ? array_slice($rows, 0, $take) : [];
            $remaining -= $take;
        }

        return [
            'groups' => $windowed,
            'total' => $total,
            'has_more' => $total > $limit && $limit < self::MAX_LIMIT,
        ];
    }
}
