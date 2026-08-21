<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Helpers\Geo;
use App\Models\CaravanPark;
use App\Platform\AiSearch\Dto\Intent;

/**
 * Routes stay intents to CaravanPark::searchStays.
 */
final class StaySearchAdapter
{
    /**
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    public function search(Intent $intent, ?array $town, ?float $lat, ?float $lng): array
    {
        $townId = $town !== null ? (int) $town['id'] : null;
        $hasOrigin = $townId !== null || ($lat !== null && $lng !== null);
        if (!$hasOrigin) {
            return [];
        }

        $radius = $intent->radiusKm ?? Geo::DEFAULT_STAY_DISTANCE_KM;
        $types = $intent->stayTypeKeys !== [] ? $intent->stayTypeKeys : [null];
        $requiredFacilities = array_values(array_unique(array_map(
            static fn(string $type): string => $type === 'drinking_water' ? 'water' : ($type === 'public_toilet' ? 'toilets' : $type),
            $intent->facilityTypeKeys
        )));
        $merged = [];

        foreach ($types as $stayType) {
            $rows = CaravanPark::searchStays($townId, $lat, $lng, $stayType, null, $radius, 60, $requiredFacilities);
            // Natural requests for a free camp should include records classified
            // by either their stay type or their explicit free price.
            if ($stayType === 'free_camp') {
                $rows = array_merge($rows, CaravanPark::searchStays($townId, $lat, $lng, null, 'free', $radius, 60, $requiredFacilities));
            }
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $row['assist_origin'] = 'canonical';
                $row['assist_source'] = 'stays';
                $merged[$id] = $row;
            }
        }

        return array_values($merged);
    }
}
