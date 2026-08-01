<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Aggregate;

/**
 * Deduplicates and lightly ranks adapter results.
 */
final class ResultAggregator
{
    /**
     * @param list<array<string,mixed>> $providers
     * @param list<array<string,mixed>> $stays
     * @return array{providers:list<array<string,mixed>>,stays:list<array<string,mixed>>}
     */
    public function aggregate(array $providers, array $stays): array
    {
        $byId = [];
        foreach ($providers as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (!isset($byId[$id])) {
                $byId[$id] = $row;
                continue;
            }
            // Prefer non-inferred and closer distance when present.
            $existingInferred = (int) ($byId[$id]['is_inferred'] ?? 0);
            $newInferred = (int) ($row['is_inferred'] ?? 0);
            if ($existingInferred === 1 && $newInferred === 0) {
                $byId[$id] = $row;
            }
        }

        $providerList = array_values($byId);
        usort($providerList, static function (array $a, array $b): int {
            $da = $a['distance_km'] ?? null;
            $db = $b['distance_km'] ?? null;
            if ($da !== null && $db !== null) {
                return ((float) $da) <=> ((float) $db);
            }
            if ($da !== null) {
                return -1;
            }
            if ($db !== null) {
                return 1;
            }
            return strcmp((string) ($a['business_name'] ?? ''), (string) ($b['business_name'] ?? ''));
        });

        $stayById = [];
        foreach ($stays as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $stayById[$id] = $row;
            }
        }

        return [
            'providers' => $providerList,
            'stays' => array_values($stayById),
        ];
    }
}
