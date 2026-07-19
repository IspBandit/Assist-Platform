<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Billing\Contracts\BillingGatewayInterface;
use App\Billing\Exceptions\BillingException;

/**
 * Stripe gateway. Chosen as the first planned gateway because it supports
 * Australian payments, subscriptions and webhook-based billing events.
 *
 * Webhook signature verification is implemented here with no external SDK
 * (pure HMAC-SHA256 per Stripe's scheme), so verified events can be ingested
 * the moment a webhook secret is configured. The charging operations are
 * deliberately left unimplemented until the Stripe API client is wired in a
 * later phase — they throw rather than pretend to succeed.
 */
final class StripeGateway implements BillingGatewayInterface
{
    private string $secretKey;
    private string $webhookSecret;
    private int $tolerance;

    public function __construct(string $secretKey = '', string $webhookSecret = '', int $tolerance = 300)
    {
        $this->secretKey = $secretKey;
        $this->webhookSecret = $webhookSecret;
        $this->tolerance = $tolerance;
    }

    public function name(): string
    {
        return 'stripe';
    }

    public function isOperational(): bool
    {
        return $this->secretKey !== '';
    }

    /**
     * Verify a Stripe-Signature header of the form "t=...,v1=...".
     * Returns false on any malformed input, missing secret, or mismatch.
     */
    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        if ($this->webhookSecret === '' || $signatureHeader === '') {
            return false;
        }

        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $signatureHeader) as $part) {
            $pair = explode('=', trim($part), 2);
            if (count($pair) !== 2) {
                continue;
            }
            [$key, $value] = $pair;
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || !ctype_digit((string) $timestamp)) {
            return false;
        }

        // Reject events outside the replay-tolerance window.
        if ($this->tolerance > 0 && abs(time() - (int) $timestamp) > $this->tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhookSecret);

        foreach ($signatures as $candidate) {
            if (hash_equals($expected, $candidate)) {
                return true;
            }
        }

        return false;
    }

    public function parseWebhookEvent(string $payload): array
    {
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            return ['id' => '', 'type' => '', 'data' => []];
        }

        return [
            'id'   => (string) ($decoded['id'] ?? ''),
            'type' => (string) ($decoded['type'] ?? ''),
            'data' => is_array($decoded['data'] ?? null) ? $decoded['data'] : [],
        ];
    }

    public function createCustomer(array $details): array
    {
        throw new BillingException('Stripe API client not yet wired. Configure and implement in the billing activation phase.');
    }

    public function createSubscription(string $customerRef, string $priceRef, array $options = []): array
    {
        throw new BillingException('Stripe API client not yet wired. Configure and implement in the billing activation phase.');
    }

    public function cancelSubscription(string $subscriptionRef, bool $atPeriodEnd = true): array
    {
        throw new BillingException('Stripe API client not yet wired. Configure and implement in the billing activation phase.');
    }

    /** Map Stripe subscription/charge statuses onto VanAssist subscription_state values. */
    public function mapSubscriptionStatus(string $gatewayStatus): string
    {
        return match ($gatewayStatus) {
            'trialing'            => 'trialling',
            'active'              => 'active',
            'past_due'            => 'past_due',
            'unpaid'              => 'payment_failed',
            'paused'              => 'paused',
            'canceled', 'cancelled' => 'cancelled',
            'incomplete_expired'  => 'expired',
            default               => 'manually_managed',
        };
    }
}
