<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Models\Provider;

/** Direct business-name lookup for Ask queries not recognised as a service intent. */
final class ProviderNameSearchAdapter
{
    public function explicitLocationText(string $rawQuery, ?string $interpretedLocation): ?string
    {
        $matches = [];
        if (preg_match_all('/\b(?:near|in|around|at)\s+([^,?]+(?:,\s*[^?]+)?)\s*\??\z/ui', trim($rawQuery), $matches) < 1) {
            return null;
        }

        $suffix = trim((string) end($matches[1]));
        if ($suffix === '' || preg_match('/\A(?:me|my current location|current location|nearby)\z/ui', $suffix) === 1) {
            return null;
        }

        return $interpretedLocation !== null && trim($interpretedLocation) !== ''
            ? trim($interpretedLocation)
            : $suffix;
    }

    public function candidate(string $rawQuery, ?string $locationText): ?string
    {
        $candidate = trim($rawQuery);
        $candidate = (string) preg_replace(
            '/\b(?:near me|nearby|closest|nearest|around me|current location)\b/ui',
            '',
            $candidate
        );
        $candidate = (string) preg_replace(
            '/\b(?:within\s+)?\d{1,3}\s*(?:km|kilometres|kilometers)\b/ui',
            '',
            $candidate
        );
        if ($locationText !== null && $locationText !== '') {
            $markers = [];
            if (preg_match_all('/\b(?:near|in|around|at)\s+/ui', $candidate, $markers, PREG_OFFSET_CAPTURE) > 0) {
                $last = $markers[0][count($markers[0]) - 1];
                $candidate = trim(mb_substr($candidate, 0, (int) $last[1]));
            }
        }
        $candidate = (string) preg_replace(
            '/\A(?:find|show me|search for|look up|provider|business|company)\s+/ui',
            '',
            $candidate
        );
        $candidate = trim($candidate, " \t\n\r\0\x0B\"'?.!");

        if (mb_strlen($candidate) < 3 || mb_strlen($candidate) > 120
            || preg_match('/[\p{L}\p{N}]/u', $candidate) !== 1) {
            return null;
        }

        return $candidate;
    }

    /** @param list<array<string,mixed>> $rows
     *  @return list<array<string,mixed>>
     */
    public function exactMatches(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => (int) ($row['name_match_rank'] ?? 99) === 0
        ));
    }

    /** @return list<array<string,mixed>> */
    public function search(string $providerName, int $brandId, int $limit = 40): array
    {
        $rows = Provider::byNameForBrand($brandId, $providerName, $limit);
        foreach ($rows as &$row) {
            $row['assist_origin'] = 'canonical';
            $row['assist_source'] = 'providers';
            $row['assist_name_match'] = true;
            $row['is_inferred'] = 0;
        }
        unset($row);

        return $rows;
    }
}
