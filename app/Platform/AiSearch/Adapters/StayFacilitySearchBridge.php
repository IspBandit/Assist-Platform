<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Core\Database;
use App\Helpers\Geo;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Services\StayFacilityService;

/**
 * Read-only bridge from resolved stay evidence into Ask facility results.
 * It does not copy a claim into traveller_facilities or change entity ownership.
 */
final class StayFacilitySearchBridge
{
    /** @var array<string,string> */
    private const INTENT_TO_STAY_TYPE = [
        'dump_point' => 'dump_point',
        'drinking_water' => 'water',
        'public_toilet' => 'toilets',
        'public_shower' => 'showers',
        'laundry' => 'laundry',
        'fuel' => 'fuel',
        'ev_charging' => 'ev_charging',
        'barbecue' => 'barbecue',
    ];

    public function __construct(private readonly ?StayFacilityService $facilities = null) {}

    /**
     * @param list<string> $intentTypes
     * @return list<array<string,mixed>>
     */
    public function search(array $intentTypes, float $latitude, float $longitude, int $radiusKm): array
    {
        if (!Database::tableExists('stay_facility_claims')) {
            return [];
        }
        $requested = array_intersect_key(self::INTENT_TO_STAY_TYPE, array_fill_keys($intentTypes, true));
        if ($requested === []) {
            return [];
        }

        $radiusKm = max(1, min(500, $radiusKm));
        $claimTypes = array_values(array_unique(array_values($requested)));
        $placeholders = implode(',', array_fill(0, count($claimTypes), '?'));
        $latDelta = $radiusKm / 111.32;
        $longitudeScale = max(0.01, abs(cos(deg2rad($latitude))));
        $lngDelta = min(180.0, $radiusKm / (111.32 * $longitudeScale));
        $rows = Database::select(
            'SELECT c.*, cp.name AS park_name, cp.slug AS park_slug, cp.address AS park_address, '
            . 'cp.latitude, cp.longitude, cp.source_url AS park_source_url, t.name AS town_name, '
            . 's.abbreviation AS state_abbr '
            . 'FROM stay_facility_claims c '
            . 'JOIN caravan_parks cp ON cp.id = c.park_id '
            . 'LEFT JOIN towns t ON t.id = cp.town_id LEFT JOIN states s ON s.id = cp.state_id '
            . "WHERE c.superseded_at IS NULL AND c.facility_type IN ($placeholders) "
            . "AND cp.status = 'active' AND cp.public_page_enabled = 1 AND cp.deleted_at IS NULL "
            . 'AND cp.latitude IS NOT NULL AND cp.longitude IS NOT NULL '
            . 'AND cp.latitude BETWEEN ? AND ? AND cp.longitude BETWEEN ? AND ? '
            . 'ORDER BY cp.id, c.id DESC LIMIT 1000',
            array_merge($claimTypes, [
                $latitude - $latDelta,
                $latitude + $latDelta,
                $longitude - $lngDelta,
                $longitude + $lngDelta,
            ])
        );

        $byPark = [];
        foreach ($rows as $row) {
            $byPark[(int) $row['park_id']][] = $row;
        }

        $results = [];
        $resolver = $this->facilities ?? new StayFacilityService();
        foreach ($byPark as $parkId => $claims) {
            $first = $claims[0];
            $distance = Geo::haversineExactKm(
                $latitude,
                $longitude,
                (float) $first['latitude'],
                (float) $first['longitude']
            );
            if ($distance > $radiusKm) {
                continue;
            }
            $resolved = $resolver->resolve($claims);
            foreach ($requested as $intentType => $stayType) {
                $claim = $resolved[$stayType] ?? null;
                if ($claim === null || !self::isPubliclyAvailable($claim)) {
                    continue;
                }
                $claimId = (int) $claim['id'];
                $label = StayFacilityService::TYPES[$stayType] ?? ucwords(str_replace('_', ' ', $stayType));
                $mapped = [
                    // Keep synthetic IDs distinct from canonical traveller_facilities
                    // while remaining exactly representable in browser JavaScript.
                    'id' => 1_000_000_000_000 + $claimId,
                    'facility_claim_id' => $claimId,
                    'stay_id' => $parkId,
                    'name' => (string) $first['park_name'] . ' — ' . $label,
                    'business_name' => (string) $first['park_name'] . ' — ' . $label,
                    'slug' => (string) $first['park_slug'],
                    'profile_url' => url('caravan-parks/' . (string) $first['park_slug']),
                    'facility_type' => $intentType,
                    'facility_status' => $claim['facility_status'] ?? null,
                    'facility_value' => $claim['facility_value'] ?? null,
                    'facility_details' => $claim['details'] ?? null,
                    'facility_display' => $claim['display'] ?? null,
                    'formatted_address' => $first['park_address'] ?? null,
                    'town_name' => $first['town_name'] ?? null,
                    'state_abbr' => $first['state_abbr'] ?? null,
                    'latitude' => (float) $first['latitude'],
                    'longitude' => (float) $first['longitude'],
                    'town_lat' => (float) $first['latitude'],
                    'town_lng' => (float) $first['longitude'],
                    'distance_km' => $distance,
                    'source_url' => $claim['source_url'] ?? $first['park_source_url'] ?? null,
                ];
                $results[] = ResultProvenance::annotate(
                    $mapped,
                    ResultProvenance::ORIGIN_CANONICAL,
                    'stay_facility_claims',
                    'claim:' . $claimId,
                    null,
                    (string) ($claim['source_name'] ?? 'Stay facility evidence'),
                    isset($claim['source_confidence']) ? ((int) $claim['source_confidence']) / 100 : null,
                );
            }
        }

        usort($results, static fn (array $a, array $b): int => ((float) $a['distance_km']) <=> ((float) $b['distance_km']));

        return $results;
    }

    /** @param array<string,mixed> $claim */
    public static function isPubliclyAvailable(array $claim): bool
    {
        $status = (string) ($claim['facility_status'] ?? 'unknown');
        if (!in_array($status, ['yes', 'conditional'], true)) {
            return false;
        }
        if (($claim['facility_type'] ?? '') === 'water') {
            return in_array((string) ($claim['facility_value'] ?? ''), [
                'potable', 'untreated', 'non_potable', 'seasonal',
            ], true);
        }

        return true;
    }
}
