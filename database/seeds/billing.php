<?php

declare(strict_types=1);

/**
 * CORE-004 shared membership defaults. Prices are agreed AUD cents, GST
 * inclusive. They remain descriptive configuration while ENABLE_BILLING=false;
 * no seed or migration activates a gateway or charges a provider.
 */

$verifiedFeatures = [
    'can_create_service_run' => true,
    'can_view_full_matching_requests' => true,
    'can_access_advanced_statistics' => true,
    'can_access_demand_reports' => true,
    'can_be_featured' => false,
    'can_export_data' => true,
    'can_use_priority_matching' => false,
    'can_create_caravan_park_service_days' => true,
    'can_use_custom_branding' => false,
    'can_access_api' => false,
];

$verifiedLimits = [
    'maximum_active_runs' => 5,
    'maximum_service_categories' => null,
    'maximum_service_areas' => null,
    'maximum_provider_users' => 3,
    'maximum_branches' => 3,
];

return ['plans' => [
    [
        'slug' => 'launch_access', 'internal_name' => 'Launch Access', 'public_name' => 'Launch Access',
        'description' => 'Temporary full-value access while marketplace value is built. No payment method is required and no charge is created.',
        'monthly_price_cents' => 0, 'annual_price_cents' => 0, 'display_order' => 10,
        'is_active' => 1, 'is_public' => 0, 'signup_available' => 0, 'is_recommended' => 0,
        'terms_summary' => 'Temporary no-charge launch access; providers later choose a membership or move safely to Free Listing.',
        'limits' => $verifiedLimits, 'features' => $verifiedFeatures,
    ],
    [
        'slug' => 'free_listing', 'internal_name' => 'Free Listing', 'public_name' => 'Free Listing',
        'description' => 'A permanent useful business listing with core profile, contact and discovery capabilities.',
        'monthly_price_cents' => 0, 'annual_price_cents' => 0, 'display_order' => 20,
        'is_active' => 1, 'is_public' => 1, 'signup_available' => 1, 'is_recommended' => 0,
        'terms_summary' => 'Free ongoing listing. No payment method required.',
        'limits' => ['maximum_active_runs' => 1, 'maximum_service_categories' => 3, 'maximum_service_areas' => 2, 'maximum_provider_users' => 1, 'maximum_branches' => 1],
        'features' => ['can_create_service_run' => true, 'can_view_full_matching_requests' => false, 'can_access_advanced_statistics' => false, 'can_access_demand_reports' => false, 'can_be_featured' => false, 'can_export_data' => false, 'can_use_priority_matching' => false, 'can_create_caravan_park_service_days' => false, 'can_use_custom_branding' => false, 'can_access_api' => false],
    ],
    [
        'slug' => 'founding_verified', 'internal_name' => 'Founding Verified', 'public_name' => 'Founding Verified',
        'description' => 'Protected early-provider offer with verified membership capabilities while continuously active.',
        'monthly_price_cents' => 1000, 'annual_price_cents' => 10000, 'display_order' => 30,
        'is_active' => 1, 'is_public' => 1, 'signup_available' => 0, 'is_recommended' => 0,
        'terms_summary' => '$10 monthly or $100 annual while continuously active. Charging remains unavailable until commercial acceptance.',
        'limits' => $verifiedLimits, 'features' => $verifiedFeatures,
    ],
    [
        'slug' => 'verified_provider', 'internal_name' => 'Verified Provider', 'public_name' => 'Verified Provider',
        'description' => 'Core verified membership with expanded profile, matching, reporting and multi-brand eligibility.',
        'monthly_price_cents' => 1500, 'annual_price_cents' => 15000, 'display_order' => 40,
        'is_active' => 1, 'is_public' => 1, 'signup_available' => 0, 'is_recommended' => 1,
        'terms_summary' => '$15 monthly or $150 annual. Charging remains unavailable until commercial acceptance.',
        'limits' => $verifiedLimits, 'features' => $verifiedFeatures,
    ],
    [
        'slug' => 'featured_provider', 'internal_name' => 'Featured Provider', 'public_name' => 'Featured Provider',
        'description' => 'Verified capabilities plus clearly labelled increased visibility that never overrides relevance or safety.',
        'monthly_price_cents' => 2900, 'annual_price_cents' => 29000, 'display_order' => 50,
        'is_active' => 1, 'is_public' => 1, 'signup_available' => 0, 'is_recommended' => 0,
        'terms_summary' => '$29 monthly or $290 annual. Featured placement is labelled. Charging remains unavailable until commercial acceptance.',
        'limits' => array_replace($verifiedLimits, ['maximum_active_runs' => null, 'maximum_provider_users' => 5, 'maximum_branches' => 5]),
        'features' => array_replace($verifiedFeatures, ['can_be_featured' => true, 'can_use_priority_matching' => true]),
    ],
]];
