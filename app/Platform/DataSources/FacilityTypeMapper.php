<?php

declare(strict_types=1);

namespace App\Platform\DataSources;

/**
 * Maps government dataset rows onto traveller_facility taxonomy keys.
 */
final class FacilityTypeMapper
{
    /** @var array<string,string> */
    private const ALIASES = [
        'public_toilet' => 'public_toilet',
        'toilet' => 'public_toilet',
        'toilets' => 'public_toilet',
        'restroom' => 'public_toilet',
        'dump_point' => 'dump_point',
        'dump point' => 'dump_point',
        'dumpstation' => 'dump_point',
        'dump station' => 'dump_point',
        'sanitary dump' => 'dump_point',
        'drinking_water' => 'drinking_water',
        'potable water' => 'drinking_water',
        'water refill' => 'drinking_water',
        'public_shower' => 'public_shower',
        'shower' => 'public_shower',
        'laundry' => 'laundry',
        'rest_area' => 'rest_area',
        'rest area' => 'rest_area',
        'visitor_information' => 'visitor_information',
        'fuel' => 'fuel',
        'fuel_station' => 'fuel',
        'fuel station' => 'fuel',
        'lpg_refill' => 'lpg_refill',
        'lpg' => 'lpg_refill',
        'hospital' => 'hospital',
        'medical_centre' => 'medical_centre',
        'pharmacy' => 'pharmacy',
        'emergency_services' => 'emergency_services',
        'boat_ramp' => 'boat_ramp',
        'boat ramp' => 'boat_ramp',
        'caravan_park' => 'other_essential',
        'caravan park' => 'other_essential',
        'caravan_parking' => 'other_essential',
        'picnic_area' => 'picnic_area',
        'barbecue' => 'barbecue',
        'bbq' => 'barbecue',
        'waste_disposal' => 'waste_disposal',
        'ev_charging' => 'ev_charging',
        'weighbridge' => 'weighbridge',
    ];

    public static function normalise(?string $raw, ?string $fallback = null): string
    {
        $value = strtolower(trim((string) $raw));
        $value = str_replace(['-', '_'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;
        if (isset(self::ALIASES[$value])) {
            return self::ALIASES[$value];
        }
        $compact = str_replace(' ', '_', $value);
        if (isset(self::ALIASES[$compact])) {
            return self::ALIASES[$compact];
        }
        foreach (self::ALIASES as $alias => $key) {
            if (str_contains($value, $alias)) {
                return $key;
            }
        }
        $fallback = $fallback !== null ? self::normalise($fallback, null) : 'other_essential';
        return $fallback !== '' ? $fallback : 'other_essential';
    }
}
