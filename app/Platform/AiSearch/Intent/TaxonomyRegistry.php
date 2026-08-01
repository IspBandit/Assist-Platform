<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Intent;

/**
 * Stable taxonomy keys for providers, stays and future traveller facilities.
 */
final class TaxonomyRegistry
{
    public const VERSION = 'taxonomy_v1';

    /** @var list<string> */
    public const STAY_TYPES = [
        'caravan_park', 'campground', 'free_camp', 'showground', 'rest_area', 'farm_stay', 'other',
    ];

    /** @var list<string> */
    public const FACILITY_TYPES = [
        'public_toilet', 'dump_point', 'drinking_water', 'public_shower', 'laundry', 'rest_area',
        'visitor_information', 'fuel', 'lpg_refill', 'hospital', 'medical_centre', 'pharmacy',
        'emergency_services', 'boat_ramp', 'picnic_area', 'barbecue', 'waste_disposal',
        'ev_charging', 'weighbridge', 'other_essential',
    ];

    /** @var list<string> */
    public const ADAPTERS = ['providers', 'stays', 'traveller_facilities', 'datasets'];

    /** Known VanAssist service_categories.slug values used by intent rules. */
    /** @var list<string> */
    public const PROVIDER_CATEGORY_KEYS = [
        'dump-points', 'potable-water-refill', 'lpg-refills-and-bottle-exchange',
        'caravan-parks-and-campgrounds', 'free-and-low-cost-camps',
        'rest-areas-and-rv-friendly-parking', 'general-caravan-repairs', 'mobile-mechanics',
        'auto-electrical-and-batteries', 'tyres-and-wheels', 'towing-and-vehicle-recovery',
        'brakes-and-bearings', 'mechanical-repairs', 'diesel-mechanics', 'ev-charging',
        'fuel-and-travel-stops', 'weighbridges-and-mobile-weighing', 'roadside-assistance',
    ];

    public static function isProviderCategoryKey(string $key): bool
    {
        return in_array($key, self::PROVIDER_CATEGORY_KEYS, true);
    }

    public static function isStayTypeKey(string $key): bool
    {
        return in_array($key, self::STAY_TYPES, true);
    }

    public static function isFacilityTypeKey(string $key): bool
    {
        return in_array($key, self::FACILITY_TYPES, true);
    }

    public static function isAdapterKey(string $key): bool
    {
        return in_array($key, self::ADAPTERS, true);
    }
}
