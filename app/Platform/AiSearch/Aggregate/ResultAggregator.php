<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Aggregate;

use App\Platform\AiSearch\Provenance\ResultProvenance;

/**
 * Deduplicates and lightly ranks adapter results; preserves provenance labels.
 */
final class ResultAggregator
{
    /**
     * @param list<array<string,mixed>> $providers
     * @param list<array<string,mixed>> $stays
     * @param list<array<string,mixed>> $externals
     * @param list<array<string,mixed>> $facilities
     * @return array{
     *   providers:list<array<string,mixed>>,
     *   stays:list<array<string,mixed>>,
     *   externals:list<array<string,mixed>>,
     *   facilities:list<array<string,mixed>>
     * }
     */
    public function aggregate(array $providers, array $stays, array $externals = [], array $facilities = []): array
    {
        $byId = [];
        foreach ($providers as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            $row = $this->ensureCanonicalProvenance($row, 'providers');
            if (!isset($byId[$id])) {
                $byId[$id] = $row;
                continue;
            }
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

        $canonicalIds = [];
        foreach ($providerList as $row) {
            $canonicalIds[(int) $row['id']] = true;
        }

        $stayById = [];
        foreach ($stays as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $stayById[$id] = $this->ensureCanonicalProvenance($row, 'stays');
            }
        }

        $facilityById = [];
        foreach ($facilities as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }
            if (!isset($row['assist_provenance_label'])) {
                $row = $this->ensureCanonicalProvenance($row, 'traveller_facilities');
            }
            $facilityById[$id] = $row;
        }
        $facilityList = array_values($facilityById);
        usort($facilityList, static function (array $a, array $b): int {
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
            return strcmp((string) ($a['name'] ?? $a['business_name'] ?? ''), (string) ($b['name'] ?? $b['business_name'] ?? ''));
        });

        $externalList = [];
        $seenExternal = [];
        foreach ($externals as $row) {
            $dup = isset($row['duplicate_provider_id']) ? (int) $row['duplicate_provider_id'] : 0;
            if ($dup > 0 && isset($canonicalIds[$dup])) {
                continue;
            }
            $key = strtolower(trim(
                (string) ($row['assist_source'] ?? '') . '|' .
                (string) ($row['assist_source_record_id'] ?? $row['id'] ?? '') . '|' .
                (string) ($row['business_name'] ?? '')
            ));
            if ($key === '||' || isset($seenExternal[$key])) {
                continue;
            }
            $seenExternal[$key] = true;
            if (!isset($row['assist_provenance_label'])) {
                $origin = (string) ($row['assist_origin'] ?? ResultProvenance::ORIGIN_STAGED);
                $row = ResultProvenance::annotate(
                    $row,
                    $origin,
                    (string) ($row['assist_source'] ?? 'dataset'),
                    isset($row['assist_source_record_id']) ? (string) $row['assist_source_record_id'] : null
                );
            }
            $externalList[] = $row;
        }

        return [
            'providers' => $providerList,
            'stays' => array_values($stayById),
            'externals' => $externalList,
            'facilities' => $facilityList,
        ];
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function ensureCanonicalProvenance(array $row, string $source): array
    {
        if (!isset($row['assist_origin'])) {
            return ResultProvenance::annotate($row, ResultProvenance::ORIGIN_CANONICAL, $source);
        }
        if (!isset($row['assist_provenance_label'])) {
            $row['assist_provenance_label'] = ResultProvenance::label((string) $row['assist_origin']);
        }
        return $row;
    }
}
