<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Conservative category widening used only after an exact provider search
 * returns no rows. Callers must label these results as possible help.
 */
final class ProviderFallbackCategories
{
    /** @var list<string> */
    private const NON_REPAIR_CATEGORIES = [
        'general-caravan-repairs',
        'fuel-and-travel-stops', 'ev-charging', 'lpg-refills-and-bottle-exchange',
        'potable-water-refill', 'dump-points', 'rest-areas-and-rv-friendly-parking',
        'caravan-parks-and-campgrounds', 'free-and-low-cost-camps',
        'groceries-and-travel-supplies', 'emergency-accommodation',
        'pet-friendly-travel-and-veterinary', 'vehicle-and-caravan-washing',
    ];

    /** @param list<string> $alreadyTried @return list<string> */
    public static function related(array $alreadyTried): array
    {
        $alreadyTried = array_values(array_unique($alreadyTried));
        if (array_intersect($alreadyTried, self::NON_REPAIR_CATEGORIES) !== []) {
            return [];
        }

        $related = [
            'structural-repairs' => ['general-caravan-repairs'],
            'fibreglass-repairs' => ['general-caravan-repairs'],
            'awning-repairs' => ['general-caravan-repairs'],
            'roof-leaks' => ['general-caravan-repairs'],
            'mobile-welding-and-fabrication' => ['general-caravan-repairs'],
            '12-volt-electrical' => ['auto-electrical-and-batteries', 'general-caravan-repairs'],
            '240-volt-electrical' => ['general-caravan-repairs'],
            'solar-and-batteries' => ['auto-electrical-and-batteries', 'general-caravan-repairs'],
            'dc-dc-charging' => ['auto-electrical-and-batteries', 'general-caravan-repairs'],
            'inverters' => ['auto-electrical-and-batteries', 'general-caravan-repairs'],
            'refrigeration' => ['general-caravan-repairs'],
            'air-conditioning' => ['general-caravan-repairs'],
            'gas-appliance-servicing' => ['general-caravan-repairs'],
            'plumbing-and-water-leaks' => ['general-caravan-repairs'],
            'hot-water-systems' => ['general-caravan-repairs'],
            'toilets' => ['general-caravan-repairs'],
            'appliance-repairs' => ['general-caravan-repairs'],
            'starlink-and-communications' => ['auto-electrical-and-batteries', 'general-caravan-repairs'],
            'brakes-and-bearings' => ['mechanical-repairs', 'mobile-mechanics'],
            'suspension' => ['mechanical-repairs', 'mobile-mechanics'],
            'tyres-and-wheels' => ['mechanical-repairs', 'mobile-mechanics'],
            'diesel-mechanics' => ['mechanical-repairs', 'mobile-mechanics'],
            'towing-and-vehicle-recovery' => ['roadside-assistance'],
            '4wd-and-remote-area-recovery' => ['roadside-assistance'],
            'general-servicing' => ['mechanical-repairs', 'mobile-mechanics'],
            'pre-trip-inspection' => ['general-servicing', 'mechanical-repairs'],
            'roadworthy-inspection' => ['general-servicing', 'mechanical-repairs'],
            'auto-electrical-and-batteries' => ['12-volt-electrical'],
            'mechanical-repairs' => ['general-servicing', 'mobile-mechanics'],
            'mobile-mechanics' => ['mechanical-repairs', 'general-servicing'],
        ];

        $categories = [];
        foreach ($alreadyTried as $slug) {
            $categories = array_merge($categories, $related[$slug] ?? []);
        }

        return array_values(array_diff(array_unique($categories), $alreadyTried));
    }
}
