<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\TowSmart\TowingCombinationCalculator;
use App\TowSmart\TowingCombinationInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TowingCombinationCalculatorTest extends TestCase
{
    public function testCombinationWithinKnownLimits(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3200, 6000, 3000, 300, 2600, 2500, 2250, 200, 2200
        ));

        self::assertTrue($result->withinKnownLimits());
        self::assertSame(4800.0, $result->combinationMassKg);
        self::assertSame(2800.0, $result->vehicleMassIncludingTowballKg);
        self::assertSame(400.0, $result->marginsKg['vehicle_gvm']);
        self::assertSame(1200.0, $result->marginsKg['vehicle_gcm']);
    }

    public function testTowballDownloadCanExceedVehicleGvm(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3000, 6000, 3000, 350, 2850, 2800, 2500, 250
        ));

        self::assertSame('likely_exceeds_entered_limit', $result->status);
        self::assertSame(-100.0, $result->marginsKg['vehicle_gvm']);
        self::assertStringContainsString('vehicle_gvm is exceeded', implode(' ', $result->warnings));
    }

    public function testNearLimitIsReported(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3000, 6000, 3000, 300, 2600, 2800, 2550, 250
        ));

        self::assertSame('close_to_entered_limit', $result->status);
        self::assertSame(50.0, $result->marginsKg['towball_limit']);
    }

    public function testGtmCannotExceedAtm(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TowingCombinationInput(3000, 6000, 3000, 300, 2500, 2000, 2100, 200);
    }

    public function testNegativeValuesAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TowingCombinationInput(3000, 6000, 3000, 300, -1, 2000, 1800, 200);
    }

    public function testOptionalComponentAndAxleLimitsAreAssessed(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3200, 6000, 3000, 300, 2500, 2600, 2350, 220, 2200,
            2800, 3500, 1500, 1250, 1900, 1600, 2500, 1980
        ));

        self::assertSame('within_entered_limits', $result->status);
        self::assertSame('high_for_entered_mass_limits', $result->confidence);
        self::assertSame([], $result->missingChecks);
        self::assertArrayHasKey('towbar_rating', $result->checks);
        self::assertArrayHasKey('rear_axle', $result->checks);
        self::assertArrayHasKey('trailer_axle_group', $result->checks);
    }

    public function testAxleLimitAndActualMustBeEnteredTogether(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TowingCombinationInput(3200, 6000, 3000, 300, 2500, 2600, 2350, 220, 2400, null, null, 1500);
    }
}
