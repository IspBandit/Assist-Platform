<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Intent;

/**
 * Stable taxonomy keys for providers, stays and traveller facilities.
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

    /** Complete seeded VanAssist service_categories.slug catalogue. */
    /** @var list<string> */
    public const PROVIDER_CATEGORY_KEYS = [
        'general-caravan-repairs', '12-volt-electrical', '240-volt-electrical',
        'solar-and-batteries', 'dc-dc-charging', 'inverters', 'refrigeration',
        'air-conditioning', 'gas-appliance-servicing', 'plumbing-and-water-leaks',
        'hot-water-systems', 'toilets', 'brakes-and-bearings', 'suspension',
        'tyres-and-wheels', 'structural-repairs', 'fibreglass-repairs',
        'awning-repairs', 'roof-leaks', 'appliance-repairs',
        'starlink-and-communications', 'insurance-repairs', 'pre-trip-inspection',
        'general-servicing', 'mechanical-repairs', 'trailer-and-engineering',
        'roadside-assistance', 'roadworthy-inspection', 'fuel-and-travel-stops',
        'ev-charging', 'lpg-refills-and-bottle-exchange', 'potable-water-refill',
        'dump-points', 'rest-areas-and-rv-friendly-parking',
        'caravan-parks-and-campgrounds', 'free-and-low-cost-camps',
        'groceries-and-travel-supplies', 'emergency-accommodation',
        'pet-friendly-travel-and-veterinary', 'towing-and-vehicle-recovery',
        '4wd-and-remote-area-recovery', 'mobile-mechanics', 'diesel-mechanics',
        'auto-electrical-and-batteries', 'locksmith-and-security',
        'windscreen-and-auto-glass', 'caravan-and-rv-parts',
        'vehicle-parts-and-accessories', 'towing-equipment-and-accessories',
        'weighbridges-and-mobile-weighing', 'vehicle-and-caravan-washing',
        'caravan-storage', 'mobile-welding-and-fabrication',
        'unsure-which-service-is-needed',
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
