<?php

declare(strict_types=1);

namespace App\Billing\Contracts;

/**
 * Contract every billing gateway must implement. Provider-account logic depends
 * ONLY on this interface, never on Stripe-specific classes, so new gateways can
 * be added without touching controllers or provider/subscription services.
 *
 * Methods return gateway-neutral arrays of external references. Persisting and
 * interpreting those references is the caller's responsibility.
 */
interface BillingGatewayInterface
{
    /** Machine name, e.g. "none" or "stripe". */
    public function name(): string;

    /** Whether this gateway can actually process payments right now. */
    public function isOperational(): bool;

    /**
     * Verify an inbound webhook signature against the raw request body.
     * MUST return false (never throw) when verification cannot be performed.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool;

    /**
     * Parse a verified webhook body into a normalised event:
     *   ['id' => string, 'type' => string, 'data' => array]
     */
    public function parseWebhookEvent(string $payload): array;

    /** Create a billing customer; returns ['external_ref' => string]. */
    public function createCustomer(array $details): array;

    /** Create a subscription; returns ['external_ref' => string, 'status' => string]. */
    public function createSubscription(string $customerRef, string $priceRef, array $options = []): array;

    /** Cancel a subscription (immediately or at period end). */
    public function cancelSubscription(string $subscriptionRef, bool $atPeriodEnd = true): array;

    /** Map a gateway subscription status onto a VanAssist subscription_state value. */
    public function mapSubscriptionStatus(string $gatewayStatus): string;
}
