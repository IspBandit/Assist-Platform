<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Billing\BillingManager;
use App\Billing\Entitlements;
use App\Billing\Gateways\NullGateway;
use App\Core\Config;
use App\Services\PlanEntitlementService;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the free-launch contract: while billing is disabled, the entitlement
 * service grants everything and no real gateway is active. These checks run
 * without a database because the disabled path never queries it.
 */
final class BillingEntitlementTest extends TestCase
{
    protected function setUp(): void
    {
        Config::set('billing.enabled', false);
        Config::set('billing.gateway', 'none');
        Config::set('billing.flags.featured_listings', false);
        BillingManager::reset();
    }

    public function testEveryFeatureAllowedWhenBillingDisabled(): void
    {
        $service = new PlanEntitlementService();
        $this->assertFalse($service->gatingEnabled());

        foreach (Entitlements::features() as $feature) {
            $this->assertTrue(
                $service->can(999, $feature),
                "Feature {$feature} should be allowed during the free launch."
            );
        }
    }

    public function testAllLimitsUnlimitedWhenBillingDisabled(): void
    {
        $service = new PlanEntitlementService();
        foreach (Entitlements::limits() as $limit) {
            $this->assertNull($service->limit(999, $limit), "Limit {$limit} should be unlimited.");
            $this->assertTrue($service->withinLimit(999, $limit));
        }
    }

    public function testGatewayIsNullWhenDisabled(): void
    {
        $gateway = BillingManager::gateway();
        $this->assertInstanceOf(NullGateway::class, $gateway);
        $this->assertFalse($gateway->isOperational());
        $this->assertFalse($gateway->verifyWebhookSignature('{}', 't=1,v1=abc'));
        $this->assertFalse(BillingManager::featureEnabled('featured_listings'));
    }

    public function testStripeSignatureVerification(): void
    {
        $secret = 'whsec_test_secret';
        $gateway = new \App\Billing\Gateways\StripeGateway('sk_test', $secret, 0);

        $payload = '{"id":"evt_1","type":"invoice.paid"}';
        $timestamp = (string) time();
        $signature = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        $header = 't=' . $timestamp . ',v1=' . $signature;

        $this->assertTrue($gateway->verifyWebhookSignature($payload, $header));
        $this->assertFalse($gateway->verifyWebhookSignature($payload, 't=' . $timestamp . ',v1=deadbeef'));

        $parsed = $gateway->parseWebhookEvent($payload);
        $this->assertSame('evt_1', $parsed['id']);
        $this->assertSame('invoice.paid', $parsed['type']);
    }
}
