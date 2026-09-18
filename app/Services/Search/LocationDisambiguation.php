<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Helps travellers choose among same-named towns (Emerald, Longreach, …)
 * without guessing the state.
 */
final class LocationDisambiguation
{
    /**
     * @param list<array<string,mixed>> $towns
     * @return list<array{name:string,state_abbr:string,label:string,slug:string}>
     */
    public static function choices(array $towns): array
    {
        $out = [];
        foreach ($towns as $town) {
            $name = trim((string) ($town['name'] ?? ''));
            $state = trim((string) ($town['state_abbr'] ?? ''));
            if ($name === '') {
                continue;
            }
            $out[] = [
                'name' => $name,
                'state_abbr' => $state,
                'label' => $state !== '' ? $name . ', ' . $state : $name,
                'slug' => (string) ($town['slug'] ?? ''),
            ];
        }

        return $out;
    }

    /**
     * Rewrite an Ask query so the place becomes an unambiguous "Town ST" label.
     */
    public static function rewriteQuery(string $rawQuery, string $townName, string $stateAbbr = ''): string
    {
        $rawQuery = trim((string) preg_replace('/\s+/u', ' ', $rawQuery));
        $label = trim($townName . ($stateAbbr !== '' ? ' ' . $stateAbbr : ''));
        if ($rawQuery === '' || $label === '') {
            return $label !== '' ? 'near ' . $label : $rawQuery;
        }

        $quoted = preg_quote($townName, '/');
        $replaced = (string) preg_replace(
            '/\b(?:near|around|in)\s+' . $quoted . '\b(?:\s+(?:ACT|NSW|NT|QLD|SA|TAS|VIC|WA))?\b/iu',
            'near ' . $label,
            $rawQuery,
            1,
            $count
        );
        if ($count > 0) {
            return $replaced;
        }

        $stripped = (string) preg_replace('/\b' . $quoted . '\b(?:\s+(?:ACT|NSW|NT|QLD|SA|TAS|VIC|WA))?\b/iu', '', $rawQuery);
        $stripped = trim((string) preg_replace('/\s+/u', ' ', $stripped));
        if ($stripped === '') {
            return 'near ' . $label;
        }

        return rtrim($stripped, ' ,.-') . ' near ' . $label;
    }
}
