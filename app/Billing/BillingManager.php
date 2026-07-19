<?php

declare(strict_types=1);

namespace App\Billing;

use App\Billing\Contracts\BillingGatewayInterface;
use App\Billing\Gateways\NullGateway;
use App\Billing\Gateways\StripeGateway;

/**
 * Central entry point for the billing subsystem. Resolves the configured
 * gateway and answers the master "is billing on?" question. Everything else
 * (controllers, views, entitlement service) consults this rather than reading
 * config or env directly, so the on/off behaviour is defined in one place.
 */
final class BillingManager
{
    private static ?BillingGatewayInterface $gateway = null;

    /** Master switch from ENABLE_BILLING. When false the platform is free. */
    public static function enabled(): bool
    {
        return (bool) config('billing.enabled', false);
    }

    /** Whether a specific granular billing feature flag is enabled. */
    public static function featureEnabled(string $flag): bool
    {
        if (!self::enabled()) {
            return false;
        }
        return (bool) config('billing.flags.' . $flag, false);
    }

    public static function gateway(): BillingGatewayInterface
    {
        if (self::$gateway !== null) {
            return self::$gateway;
        }

        $name = self::enabled() ? (string) config('billing.gateway', 'none') : 'none';

        self::$gateway = match ($name) {
            'stripe' => new StripeGateway(
                (string) config('billing.stripe.secret_key', ''),
                (string) config('billing.stripe.webhook_secret', '')
            ),
            default => new NullGateway(),
        };

        return self::$gateway;
    }

    /** Reset the resolved gateway (used in tests). */
    public static function reset(): void
    {
        self::$gateway = null;
    }
}
