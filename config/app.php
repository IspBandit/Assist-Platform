<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'name'        => Env::get('APP_NAME', 'VanAssist'),
    'env'         => Env::get('APP_ENV', 'production'),
    'debug'       => (bool) Env::get('APP_DEBUG', false),
    'url'         => rtrim((string) Env::get('APP_URL', 'http://localhost'), '/'),
    'key'         => Env::get('APP_KEY', ''),
    'release'     => Env::get('APP_RELEASE', ''),
    'timezone'    => 'Australia/Brisbane',
    'launch_mode' => Env::get('LAUNCH_MODE', 'private'), // private|provider-onboarding|local-pilot|public

    'tagline'  => 'Caravan help, wherever you travel.',

    'features' => [
        'billing'                => (bool) Env::get('ENABLE_BILLING', false),
        'provider_subscriptions' => (bool) Env::get('ENABLE_PROVIDER_SUBSCRIPTIONS', false),
        'provider_trials'        => (bool) Env::get('ENABLE_PROVIDER_TRIALS', false),
        'featured_listings'      => (bool) Env::get('ENABLE_FEATURED_LISTINGS', false),
        'featured_runs'          => (bool) Env::get('ENABLE_FEATURED_RUNS', false),
        'booking_fees'           => (bool) Env::get('ENABLE_BOOKING_FEES', false),
        'commissions'            => (bool) Env::get('ENABLE_COMMISSIONS', false),
        'customer_payments'      => (bool) Env::get('ENABLE_CUSTOMER_PAYMENTS', false),
        'provider_payouts'       => (bool) Env::get('ENABLE_PROVIDER_PAYOUTS', false),
        'discount_codes'         => (bool) Env::get('ENABLE_DISCOUNT_CODES', false),
        'annual_billing'         => (bool) Env::get('ENABLE_ANNUAL_BILLING', false),
        'sms'                    => (bool) Env::get('ENABLE_SMS', false),
        'reviews'                => (bool) Env::get('ENABLE_REVIEWS', false),
        'public_phone'           => (bool) Env::get('ENABLE_PUBLIC_PHONE', false),
    ],
];
