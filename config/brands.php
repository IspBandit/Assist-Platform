<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'default' => (string) Env::get('ASSIST_DEFAULT_BRAND', 'vanassist'),
    'explicit' => Env::get('ASSIST_BRAND'),
    'strict_hosts' => (bool) Env::get('ASSIST_STRICT_BRAND_HOSTS', false),
    'allow_development_fallback' => (bool) Env::get('ASSIST_ALLOW_BRAND_QUERY', false),

    'registry' => [
        'vanassist' => [
            'database_id' => 1,
            'name' => 'VanAssist',
            'legal_name' => (string) Env::get('VANASSIST_LEGAL_NAME', 'VanAssist'),
            'short_name' => 'VanAssist',
            'status' => 'active',
            'url' => rtrim((string) Env::get('VANASSIST_URL', 'https://vanassist.com.au'), '/'),
            'domains' => [
                'primary' => (string) Env::get('VANASSIST_DOMAIN', 'vanassist.com.au'),
                'www' => 'www.vanassist.com.au',
                'local' => 'vanassist.test',
                'legacy' => 'vanassist.condrendigital.com.au',
            ],
            'assets' => [
                'logo' => '/assets/brands/vanassist/mark.svg',
                'icon' => '/assets/brands/vanassist/mark.svg',
                'favicon' => '/assets/brands/vanassist/mark.svg',
            ],
            'theme' => [
                'brand' => '#0f6e6e',
                'brand_emphasis' => '#0b5757',
                'accent' => '#b45309',
                'surface' => '#fbf8f1',
                'text' => '#2b2f33',
                'focus' => '#b45309',
            ],
            'metadata' => [
                'wordmark_prefix' => 'Van',
                'wordmark_accent' => 'Assist',
                'header_descriptor' => 'RV SERVICE NETWORK',
                'tagline' => 'Caravan help, wherever you travel.',
                'description' => 'Find caravan and RV service providers across regional Australia.',
                'social_image' => '/assets/img/hero-home.jpg',
            ],
            'contact' => [
                'support_email' => (string) Env::get('VANASSIST_SUPPORT_EMAIL', ''),
                'sender_email' => (string) Env::get('MAIL_FROM_ADDRESS', ''),
                'sender_name' => (string) Env::get('MAIL_FROM_NAME', 'VanAssist'),
            ],
            'legal' => [
                'privacy_path' => '/privacy-policy',
                'terms_path' => '/terms-of-use',
            ],
            'navigation' => [
                ['label' => 'Find a provider', 'path' => '/providers'],
                ['label' => 'Request assistance', 'path' => '/request-assistance'],
                ['label' => 'Service runs', 'path' => '/service-runs'],
            ],
            'footer' => [
                ['label' => 'Privacy', 'path' => '/privacy'],
                ['label' => 'Terms', 'path' => '/terms'],
                ['label' => 'Contact', 'path' => '/contact'],
            ],
            'features' => [
                'identity.shared' => false,
                'providers.messaging' => true,
                'reviews.enabled' => (bool) Env::get('ENABLE_REVIEWS', false),
                'billing.enabled' => (bool) Env::get('ENABLE_BILLING', false),
                'advertising.enabled' => true,
                'service_history.enabled' => false,
                'reminders.enabled' => false,
            ],
            'modules' => [
                'public_application' => true,
                'providers' => true,
                'requests' => true,
                'service_runs' => true,
                'parks' => true,
                'cms' => true,
                'admin' => true,
                'towing_tools' => false,
                'trailer_marketplace' => false,
            ],
            'analytics' => [
                'measurement_id' => (string) Env::get('VANASSIST_ANALYTICS_ID', ''),
            ],
            'search' => [
                'provider_index' => 'vanassist_providers',
            ],
            'storage_namespace' => 'vanassist',
        ],

        'towsmart' => [
            'database_id' => 2,
            'name' => 'TowSmart',
            'legal_name' => (string) Env::get('TOWSMART_LEGAL_NAME', Env::get('TOWWISE_LEGAL_NAME', 'TowSmart')),
            'short_name' => 'TowSmart',
            'status' => 'active',
            'url' => rtrim((string) Env::get('TOWSMART_URL', Env::get('TOWWISE_URL', 'https://towsmart.com.au')), '/'),
            'domains' => [
                'primary' => (string) Env::get('TOWSMART_DOMAIN', Env::get('TOWWISE_DOMAIN', 'towsmart.com.au')),
                'www' => 'www.towsmart.com.au',
                'local' => 'towsmart.test',
                'legacy_local' => 'towwise.test',
            ],
            'assets' => [
                'logo' => '/assets/brands/towsmart/mark.svg',
                'icon' => '/assets/brands/towsmart/mark.svg',
                'favicon' => '/assets/brands/towsmart/mark.svg',
            ],
            'theme' => [
                'brand' => '#1d4ed8',
                'brand_emphasis' => '#1e3a8a',
                'accent' => '#f59e0b',
                'surface' => '#eff6ff',
                'text' => '#172554',
                'focus' => '#b45309',
            ],
            'metadata' => [
                'wordmark_prefix' => 'Tow',
                'wordmark_accent' => 'Smart',
                'header_descriptor' => 'TOWING SAFETY & GUIDANCE',
                'tagline' => 'Tow smarter. Tow safer.',
                'description' => 'Towing calculations, compatibility, education and safety tools.',
                'social_image' => '/assets/brands/towsmart/mark.svg',
            ],
            'contact' => [
                'support_email' => (string) Env::get('TOWSMART_SUPPORT_EMAIL', Env::get('TOWWISE_SUPPORT_EMAIL', '')),
                'sender_email' => (string) Env::get('TOWSMART_MAIL_FROM_ADDRESS', 'support@towsmart.com.au'),
                'sender_name' => 'TowSmart',
            ],
            'legal' => [
                'privacy_path' => '/privacy-policy',
                'terms_path' => '/terms-of-use',
            ],
            'navigation' => [
                ['label' => 'Home', 'path' => '/'],
                ['label' => 'Weight calculator', 'path' => '/calculator'],
                ['label' => 'Towing specialists', 'path' => '/providers'],
                ['label' => 'My combinations', 'path' => '/account/towing-combinations'],
            ],
            'footer' => [
                ['label' => 'Privacy', 'path' => '/privacy'],
                ['label' => 'Terms', 'path' => '/terms'],
            ],
            'features' => [
                'identity.shared' => false,
                'providers.messaging' => false,
                'reviews.enabled' => false,
                'billing.enabled' => false,
                'advertising.enabled' => false,
                'service_history.enabled' => false,
                'reminders.enabled' => false,
            ],
            'modules' => [
                'public_application' => true,
                'providers' => true,
                'requests' => false,
                'service_runs' => false,
                'parks' => false,
                'cms' => true,
                'admin' => true,
                'towing_tools' => true,
                'trailer_marketplace' => false,
            ],
            'analytics' => [
                'measurement_id' => (string) Env::get('TOWSMART_ANALYTICS_ID', Env::get('TOWWISE_ANALYTICS_ID', '')),
            ],
            'search' => [
                'provider_index' => 'towsmart_resources',
            ],
            // Retained to avoid orphaning any files written before the rename.
            'storage_namespace' => 'towwise',
        ],

        'trailerwise' => [
            'database_id' => 3,
            'name' => 'TrailerWise',
            'legal_name' => (string) Env::get('TRAILERWISE_LEGAL_NAME', 'TrailerWise'),
            'short_name' => 'TrailerWise',
            'status' => 'active',
            'url' => rtrim((string) Env::get('TRAILERWISE_URL', 'https://trailerwise.com.au'), '/'),
            'domains' => [
                'primary' => (string) Env::get('TRAILERWISE_DOMAIN', 'trailerwise.com.au'),
                'www' => 'www.trailerwise.com.au',
                'local' => 'trailerwise.test',
            ],
            'assets' => [
                'logo' => '/assets/brands/trailerwise/mark.svg',
                'icon' => '/assets/brands/trailerwise/mark.svg',
                'favicon' => '/assets/brands/trailerwise/mark.svg',
            ],
            'theme' => [
                'brand' => '#7c3aed',
                'brand_emphasis' => '#5b21b6',
                'accent' => '#ea580c',
                'surface' => '#faf5ff',
                'text' => '#2e1065',
                'focus' => '#c2410c',
            ],
            'metadata' => [
                'wordmark_prefix' => 'Trailer',
                'wordmark_accent' => 'Wise',
                'header_descriptor' => 'TRAILER SERVICE NETWORK',
                'tagline' => 'Smarter trailer ownership.',
                'description' => 'Trailer businesses, services, listings and compliance resources.',
                'social_image' => '/assets/brands/trailerwise/mark.svg',
            ],
            'contact' => [
                'support_email' => (string) Env::get('TRAILERWISE_SUPPORT_EMAIL', ''),
                'sender_email' => (string) Env::get('TRAILERWISE_MAIL_FROM_ADDRESS', 'support@trailerwise.com.au'),
                'sender_name' => 'TrailerWise',
            ],
            'legal' => [
                'privacy_path' => '/privacy-policy',
                'terms_path' => '/terms-of-use',
            ],
            'navigation' => [
                ['label' => 'Home', 'path' => '/'],
                ['label' => 'Find trailer services', 'path' => '/providers'],
                ['label' => 'Trailers for sale', 'path' => '/marketplace'],
            ],
            'footer' => [
                ['label' => 'Privacy', 'path' => '/privacy'],
                ['label' => 'Terms', 'path' => '/terms'],
            ],
            'features' => [
                'identity.shared' => false,
                'providers.messaging' => false,
                'reviews.enabled' => false,
                'billing.enabled' => false,
                'advertising.enabled' => false,
                'service_history.enabled' => false,
                'reminders.enabled' => false,
            ],
            'modules' => [
                'public_application' => true,
                'providers' => true,
                'requests' => false,
                'service_runs' => false,
                'parks' => false,
                'cms' => true,
                'admin' => true,
                'towing_tools' => false,
                'trailer_marketplace' => true,
            ],
            'analytics' => [
                'measurement_id' => (string) Env::get('TRAILERWISE_ANALYTICS_ID', ''),
            ],
            'search' => [
                'provider_index' => 'trailerwise_businesses',
            ],
            'storage_namespace' => 'trailerwise',
        ],
    ],
];
