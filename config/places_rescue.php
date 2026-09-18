<?php

declare(strict_types=1);

/**
 * Demand-driven Places rescue query phrases keyed by public service_categories.slug.
 * Used only when provider_places_rescue is enabled (ADR 0042).
 */
return [
    'weak_result_threshold' => 3,
    'max_results' => 8,
    'auto_publish_unclaimed' => true,
    'attribution' => 'Results include public business details from Google. Confirm details before travelling.',
    'queries' => [
        'refrigeration' => 'fridge refrigeration repair caravan RV',
        'air-conditioning' => 'caravan air conditioning repair',
        'gas-appliance-servicing' => 'caravan gas appliance refrigeration',
        'appliance-repairs' => 'caravan appliance repair',
        'general-caravan-repairs' => 'caravan repairs RV service',
        'mobile-mechanics' => 'mobile caravan mechanic',
        'mechanical-repairs' => 'caravan mechanical repairs',
        'diesel-mechanics' => 'mobile diesel mechanic caravan',
        'auto-electrical-and-batteries' => 'auto electrician caravan 12 volt',
        '12-volt-electrical' => 'caravan 12 volt auto electrician',
        'brakes-and-bearings' => 'trailer brakes bearings caravan',
        'tyres-and-wheels' => 'caravan trailer tyres',
        'roadside-assistance' => 'roadside assistance caravan',
        'plumbing-and-water-leaks' => 'caravan plumbing water leak',
        'hot-water-systems' => 'caravan hot water system repair',
        'solar-and-batteries' => 'caravan solar battery specialist',
        'awning-repairs' => 'caravan awning repairs',
        'roof-leaks' => 'caravan roof leak repair',
        'structural-repairs' => 'caravan structural repairs',
        'general-servicing' => 'caravan service workshop',
        'roadworthy-inspection' => 'caravan roadworthy safety certificate',
        'fuel-and-travel-stops' => 'fuel truck stop caravan',
        'ev-charging' => 'EV charging station',
        'lpg-refills-and-bottle-exchange' => 'LPG bottle exchange refill',
    ],
];
