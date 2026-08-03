<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Routing;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;

/**
 * Selects executable adapters from a validated intent.
 * traveller_facilities is executable when assist_ai_traveller_facilities is on (AI-6).
 */
final class SearchRouter
{
    /**
     * @return list<string>
     */
    public function adaptersFor(Intent $intent): array
    {
        $adapters = [];
        foreach ($intent->adapterKeys as $key) {
            if ($key === 'providers' || $key === 'stays' || $key === 'datasets') {
                $adapters[] = $key;
            }
            if ($key === 'traveller_facilities' && TravellerFacilitiesFeature::enabled()) {
                $adapters[] = $key;
            }
        }
        if ($adapters === [] && $intent->providerCategoryKeys !== []) {
            $adapters[] = 'providers';
        }
        if ($adapters === [] && $intent->stayTypeKeys !== []) {
            $adapters[] = 'stays';
        }
        if ($adapters === [] && $intent->facilityTypeKeys !== [] && TravellerFacilitiesFeature::enabled()) {
            $adapters[] = 'traveller_facilities';
        }
        return array_values(array_unique($adapters));
    }

    /**
     * When local results are weak/zero and datasets are enabled, augment routing.
     *
     * @param list<string> $adapters
     * @return list<string>
     */
    public function withDatasetAugment(array $adapters, int $localCount, bool $datasetsEnabled): array
    {
        if (!$datasetsEnabled) {
            return $adapters;
        }
        $threshold = max(0, (int) config('ai_search.weak_result_threshold', 3));
        if ($localCount > $threshold) {
            return $adapters;
        }
        if (!in_array('datasets', $adapters, true)) {
            $adapters[] = 'datasets';
        }
        return array_values(array_unique($adapters));
    }
}
