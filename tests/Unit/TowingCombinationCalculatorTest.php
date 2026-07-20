<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\TowWise\TowingCombinationCalculator;
use App\TowWise\TowingCombinationInput;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TowingCombinationCalculatorTest extends TestCase
{
    public function testCombinationWithinKnownLimits(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3200, 6000, 3000, 300, 2600, 2500, 2250, 250
        ));

        self::assertTrue($result->withinKnownLimits());
        self::assertSame(5100.0, $result->combinationMassKg);
        self::assertSame(2850.0, $result->vehicleMassIncludingTowballKg);
        self::assertSame(350.0, $result->marginsKg['vehicle_gvm']);
        self::assertSame(900.0, $result->marginsKg['vehicle_gcm']);
    }

    public function testTowballDownloadCanExceedVehicleGvm(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3000, 6000, 3000, 350, 2850, 2800, 2500, 250
        ));

        self::assertSame('exceeds_known_limit', $result->status);
        self::assertSame(-100.0, $result->marginsKg['vehicle_gvm']);
        self::assertStringContainsString('vehicle_gvm is exceeded', implode(' ', $result->warnings));
    }

    public function testNearLimitIsReported(): void
    {
        $result = (new TowingCombinationCalculator())->calculate(new TowingCombinationInput(
            3000, 6000, 3000, 300, 2600, 2800, 2550, 250
        ));

        self::assertSame('near_known_limit', $result->status);
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
}
