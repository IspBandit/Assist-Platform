<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Helpers\Geo;
use App\Models\Provider;
use App\Models\ServiceCategory;
use App\Platform\AiSearch\Dto\Intent;
use App\Services\Search\ProviderSearchRadiusLadder;

/**
 * Routes provider category intents to existing Provider model queries.
 * Uses the shared radius ladder when the traveller did not set a distance.
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

        $hasOrigin = $lat !== null && $lng !== null;
        // A named place that could not be resolved must never degrade into an
        // Australia-wide provider search. It is safer to return no result and
        // ask for a clearer location than show businesses hundreds of km away.
        if (!$hasOrigin && $town === null && $intent->locationText !== null && $intent->locationText !== '') {
            return [];
        }

        if ($hasOrigin) {
            $explicit = $intent->radiusKm;
            $ladder = ProviderSearchRadiusLadder::expand(
                function (int $km) use ($keys, $lat, $lng): array {
                    return $this->searchCategoriesNear($keys, (float) $lat, (float) $lng, $km);
                },
                $explicit
            );

            return $ladder['rows'];
        }

        return $this->searchCategoriesTownOrNational($keys, $town);
    }

    /**
     * Same as search(), but also returns ladder metadata for messaging.
     *
     * @param array<string,mixed>|null $town
     * @return array{rows:list<array<string,mixed>>,radius_km:?int,expanded:bool,message:?string}
     */
    public function searchWithMeta(Intent $intent, ?array $town, ?float $lat, ?float $lng): array
    {
        $keys = $intent->providerCategoryKeys;
        if ($keys === []) {
            return ['rows' => [], 'radius_km' => null, 'expanded' => false, 'message' => null];
        }

        $hasOrigin = $lat !== null && $lng !== null;
        if (!$hasOrigin && $town === null && $intent->locationText !== null && $intent->locationText !== '') {
            return ['rows' => [], 'radius_km' => null, 'expanded' => false, 'message' => null];
        }

        if ($hasOrigin) {
            $ladder = ProviderSearchRadiusLadder::expand(
                function (int $km) use ($keys, $lat, $lng): array {
                    return $this->searchCategoriesNear($keys, (float) $lat, (float) $lng, $km);
                },
                $intent->radiusKm
            );

            return [
                'rows' => $ladder['rows'],
                'radius_km' => $ladder['radius_km'],
                'expanded' => $ladder['expanded'],
                'message' => $ladder['message'],
            ];
        }

        $rows = $this->searchCategoriesTownOrNational($keys, $town);

        return ['rows' => $rows, 'radius_km' => null, 'expanded' => false, 'message' => null];
    }

    /**
     * @param list<string> $keys
     * @return list<array<string,mixed>>
     */
    private function searchCategoriesNear(array $keys, float $lat, float $lng, int $radius): array
    {
        $merged = [];
        foreach ($keys as $slug) {
            $category = ServiceCategory::findActiveBySlug($slug);
            if ($category === null) {
                continue;
            }
            $rows = Provider::forCategoryNear((int) $category['id'], $lat, $lng, $radius);
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

        $filter = [
            'scope' => 'km',
            'km' => $radius,
            'town_radius_km' => (int) config('geo.default_town_radius_km', 20),
        ];

        return Geo::applyDistanceFilter(array_values($merged), $lat, $lng, $filter, null);
    }

    /**
     * @param list<string> $keys
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    private function searchCategoriesTownOrNational(array $keys, ?array $town): array
    {
        $merged = [];
        foreach ($keys as $slug) {
            $category = ServiceCategory::findActiveBySlug($slug);
            if ($category === null) {
                continue;
            }
            $categoryId = (int) $category['id'];
            $rows = $town !== null
                ? Provider::forCategory($categoryId, (int) $town['id'])
                : Provider::forCategory($categoryId);
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

        return array_values($merged);
    }
}
