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
            ['fabrication', 'performance-workshops', 'vehicle-inspections', 'motorcycle-workshops', 'suspension'],
            RegulatorySponsor::categoryKeys('localtorque', 'modifications', 'motorcycle', 'Suspension lift')
        );
    }

    public function testDoesNotInventContextForAnUnfilteredLibrary(): void
    {
        self::assertSame([], RegulatorySponsor::categoryKeys('localtorque', '', '', ''));
    }

    public function testOnlyHttpDestinationsCanBePublished(): void
    {
        self::assertTrue(RegulatorySponsor::safeDestination('https://provider.example/services'));
        self::assertFalse(RegulatorySponsor::safeDestination('javascript:alert(1)'));
        self::assertFalse(RegulatorySponsor::safeDestination('/relative'));
    }
}
