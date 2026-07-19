<?php

declare(strict_types=1);

namespace App\Billing\Gateways;

use App\Billing\Contracts\BillingGatewayInterface;
use App\Billing\Exceptions\BillingException;

/**
 * The "none" gateway used during the free launch (BILLING_GATEWAY=none).
 *
 * It never charges anyone and refuses every payment operation. Read-only,
 * non-charging behaviour (the free platform) needs none of these methods, so
 * any accidental call surfaces loudly instead of silently pretending to bill.
 */
final class NullGateway implements BillingGatewayInterface
{
    public function name(): string
    {
        return 'none';
    }

    public function isOperational(): bool
    {
        return false;
    }

    public function verifyWebhookSignature(string $payload, string $signatureHeader): bool
    {
        return false;
    }

    public function parseWebhookEvent(string $payload): array
    {
        return ['id' => '', 'type' => '', 'data' => []];
    }

    public function createCustomer(array $details): array
    {
        throw new BillingException('Billing is disabled (gateway=none); no customer can be created.');
    }

    public function createSubscription(string $customerRef, string $priceRef, array $options = []): array
    {
        throw new BillingException('Billing is disabled (gateway=none); no subscription can be created.');
    }

    public function cancelSubscription(string $subscriptionRef, bool $atPeriodEnd = true): array
    {
        throw new BillingException('Billing is disabled (gateway=none).');
    }

    public function mapSubscriptionStatus(string $gatewayStatus): string
    {
        return 'complimentary';
    }
}
