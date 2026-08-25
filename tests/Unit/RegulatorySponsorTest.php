<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RegulatorySponsor;
use PHPUnit\Framework\TestCase;

final class RegulatorySponsorTest extends TestCase
{
    public function testMapsRuleContextToRelevantProviderCategories(): void
    {
        self::assertSame(
            ['caravan-rv-repairs', 'auto-electrical', 'tyres-wheels-bearings', 'roadworthy-inspections', 'roadside-recovery', 'fabrication', 'performance-workshops', 'vehicle-inspections', 'motorcycle-workshops', 'suspension'],
            RegulatorySponsor::categoryKeys('vanassist', 'modifications', 'motorcycle', 'Suspension lift')
        );
    }

    public function testStreetRodSponsorsUseStreetRodCertificationCategories(): void
    {
        $keys = RegulatorySponsor::categoryKeys('vanassist', 'street_rods', 'street-rod', '');

        self::assertContains('street-rod-certification', $keys);
        self::assertContains('approved-vehicle-engineer', $keys);
        self::assertNotContains('performance-workshops', $keys);
    }

    public function testDoesNotInventContextForAnUnfilteredLibrary(): void
    {
        self::assertSame(
            ['caravan-rv-repairs', 'auto-electrical', 'tyres-wheels-bearings', 'roadworthy-inspections', 'roadside-recovery'],
            RegulatorySponsor::categoryKeys('vanassist', '', '', '')
        );
    }

    public function testOnlyHttpDestinationsCanBePublished(): void
    {
        self::assertTrue(RegulatorySponsor::safeDestination('https://provider.example/services'));
        self::assertFalse(RegulatorySponsor::safeDestination('javascript:alert(1)'));
        self::assertFalse(RegulatorySponsor::safeDestination('/relative'));
    }
}
