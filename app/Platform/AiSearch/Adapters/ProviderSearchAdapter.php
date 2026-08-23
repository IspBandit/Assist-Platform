<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Helpers\Geo;
use App\Models\Provider;
use App\Models\ServiceCategory;
use App\Platform\AiSearch\Dto\Intent;

/**
 * Routes provider category intents to existing Provider model queries.
 */
final class ProviderSearchAdapter
{
    /**
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    public function search(Intent $intent, ?array $town, ?float $lat, ?float $lng): array
    {
        $keys = $intent->providerCategoryKeys;
        if ($keys === []) {
            return [];
        }

        $radius = $intent->radiusKm ?? (int) config('ai_search.default_radius_km', 25);
        $hasOrigin = $lat !== null && $lng !== null;
        // A named place that could not be resolved must never degrade into an
        // Australia-wide provider search. It is safer to return no result and
        // ask for a clearer location than show businesses hundreds of km away.
        if (!$hasOrigin && $town === null && $intent->locationText !== null && $intent->locationText !== '') {
            return [];
        }
        $merged = [];

        foreach ($keys as $slug) {
            $category = ServiceCategory::findActiveBySlug($slug);
            if ($category === null) {
                continue;
            }
            $categoryId = (int) $category['id'];
            if ($hasOrigin) {
                $rows = Provider::forCategoryNear($categoryId, (float) $lat, (float) $lng, $radius);
            } elseif ($town !== null) {
                $rows = Provider::forCategory($categoryId, (int) $town['id']);
            } else {
                $rows = Provider::forCategory($categoryId);
            }
            foreach ($rows as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $row['assist_origin'] = 'canonical';
                $row['assist_source'] = 'providers';
                $row['assist_category_slug'] = $slug;
                if (!isset($merged[$id]) || (int) ($row['is_inferred'] ?? 1) === 0) {
                    $merged[$id] = $row;
                }
            }
        }

        $list = array_values($merged);
        if ($hasOrigin) {
            $filter = ['scope' => 'km', 'km' => $radius, 'town_radius_km' => (int) config('geo.default_town_radius_km', 20)];
            $list = Geo::applyDistanceFilter($list, $lat, $lng, $filter, $town !== null ? (int) $town['id'] : null);
        }

        return $list;
    }

    /**
     * Last-resort regional pool used only for provider-only intents. The UI
     * must tell users these are not exact category matches.
     *
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    public function searchRegionalTownPool(?array $town, ?float $lat, ?float $lng, int $radiusKm, int $limit = 60): array
    {
        if ($town === null || (int) ($town['id'] ?? 0) <= 0) {
            return [];
        }

        $townId = (int) $town['id'];
        $regionId = isset($town['region_id']) ? (int) $town['region_id'] : null;
        $list = Provider::inTown($townId, $regionId > 0 ? $regionId : null, $limit);
        foreach ($list as $index => $row) {
            $list[$index]['assist_origin'] = 'regional_town_pool';
            $list[$index]['assist_source'] = 'providers';
        }

        if ($lat !== null && $lng !== null) {
            $filter = [
                'scope' => 'km',
                'km' => max(1, min(500, $radiusKm)),
                'town_radius_km' => (int) config('geo.default_town_radius_km', 20),
            ];
            $list = Geo::applyDistanceFilter($list, $lat, $lng, $filter, $townId);
        }

        return $list;
    }
}
