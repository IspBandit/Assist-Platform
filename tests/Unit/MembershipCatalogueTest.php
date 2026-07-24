<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Billing\MembershipCatalogue;
use App\Services\MembershipPresentationService;
use PHPUnit\Framework\TestCase;

final class MembershipCatalogueTest extends TestCase
{
    public function testCatalogueUsesAgreedNamesAndPrices(): void
    {
        $this->assertSame(
            ['launch_access', 'free_listing', 'founding_verified', 'verified_provider', 'featured_provider'],
            MembershipCatalogue::slugs()
        );
        $this->assertSame(1000, MembershipCatalogue::find('founding_verified')['monthly_price_cents']);
        $this->assertSame(15000, MembershipCatalogue::find('verified_provider')['annual_price_cents']);
        $this->assertSame(2900, MembershipCatalogue::find('featured_provider')['monthly_price_cents']);
    }

    public function testBillingDisabledAlwaysPresentsNoChargeLaunchAccess(): void
    {
        $state = (new MembershipPresentationService())->forProvider(
            ['id' => 42],
            false,
            MembershipCatalogue::FEATURED_PROVIDER
        );

        $this->assertSame('launch_access', $state['slug']);
        $this->assertSame('Launch Access', $state['name']);
        $this->assertFalse($state['charging_enabled']);
        $this->assertStringContainsString('no automatic billing', $state['summary']);
    }

    public function testUnknownEnabledPlanFailsBackToFreeListing(): void
    {
        $state = (new MembershipPresentationService())->forProvider(['id' => 42], true, 'legacy-plan');
        $this->assertSame('free_listing', $state['slug']);
        $this->assertSame('Free Listing', $state['name']);
    }
}
