<?php

declare(strict_types=1);

use App\Helpers\Env;

/**
 * Billing / monetisation configuration.
 *
 * The whole subscription system stays dormant while ENABLE_BILLING=false:
 * nothing is charged, no checkout or portal is shown, and the entitlement
 * service grants everything so no provider feature is gated by payment.
 * Toggling these flags on later activates billing without touching the
 * provider, profile, service-area, run or request data already captured.
 */
return [
    // Master switch. When false the platform is completely free.
    'enabled' => (bool) Env::get('ENABLE_BILLING', false),

    // Active gateway: 'none' (no payments) or 'stripe'.
    'gateway' => (string) Env::get('BILLING_GATEWAY', 'none'),

    'currency' => strtoupper((string) Env::get('BILLING_CURRENCY', 'AUD')),

    // Slug of the plan a provider falls back to instead of losing data.
    'default_free_plan' => (string) Env::get('BILLING_DEFAULT_FREE_PLAN', 'founding_free'),

    // Days a provider keeps paid access after a failed payment before features lock.
    'grace_days' => (int) Env::get('BILLING_GRACE_DAYS', 14),

    // Granular feature flags. Each must be explicitly enabled; a feature is never
    // exposed merely because its database tables exist.
    'flags' => [
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
    ],

    'stripe' => [
        'secret_key'      => (string) Env::get('STRIPE_SECRET_KEY', ''),
        'publishable_key' => (string) Env::get('STRIPE_PUBLISHABLE_KEY', ''),
        'webhook_secret'  => (string) Env::get('STRIPE_WEBHOOK_SECRET', ''),
    ],

    // Australian GST / tax-invoice defaults. These seed tax_settings and may be
    // overridden by super-admins. NOT a substitute for accountant review.
    'tax' => [
        'gst_registered'    => (bool) Env::get('GST_REGISTERED', false),
        'gst_rate'          => (float) Env::get('GST_RATE', 10),
        'abn'               => (string) Env::get('BILLING_ABN', ''),
        'business_name'     => (string) Env::get('BILLING_BUSINESS_NAME', 'VanAssist'),
        'gst_inclusive'     => (bool) Env::get('GST_INCLUSIVE_PRICING', true),
        'invoice_prefix'    => (string) Env::get('INVOICE_NUMBER_PREFIX', 'VA-'),
    ],
];
