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

        $electrical = [
            '12-volt-electrical', '240-volt-electrical', 'solar-and-batteries',
            'dc-dc-charging', 'inverters', 'refrigeration', 'air-conditioning',
            'starlink-and-communications', 'auto-electrical-and-batteries',
        ];
        $vehicle = [
            'brakes-and-bearings', 'suspension', 'tyres-and-wheels',
            'diesel-mechanics', 'towing-and-vehicle-recovery', '4wd-and-remote-area-recovery',
        ];
        $servicing = [
            'mobile-mechanics', 'mechanical-repairs', 'general-servicing',
            'pre-trip-inspection', 'roadworthy-inspection',
        ];

        if (array_intersect($alreadyTried, $electrical) !== []) {
            $categories = ['general-caravan-repairs', 'auto-electrical-and-batteries'];
        } elseif (array_intersect($alreadyTried, $vehicle) !== []) {
            $categories = ['mobile-mechanics', 'mechanical-repairs', 'roadside-assistance'];
        } elseif (array_intersect($alreadyTried, $servicing) !== []) {
            $categories = ['general-caravan-repairs', 'auto-electrical-and-batteries', 'diesel-mechanics'];
        } else {
            $categories = ['general-caravan-repairs', 'mobile-mechanics'];
        }

        return array_values(array_diff($categories, $alreadyTried));
    }
}
