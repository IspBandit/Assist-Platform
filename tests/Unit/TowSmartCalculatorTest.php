<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TowSmartCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TowSmartCalculatorTest extends TestCase
{
    public function testCalculatesACombinationWithinAllKnownLimits(): void
    {
        $result = TowSmartCalculator::calculate($this->validInput());

        self::assertSame('within_limits', $result['status']);
        self::assertSame(2750.0, $result['calculated']['vehicle_loaded_mass']);
        self::assertSame(2250.0, $result['calculated']['trailer_gtm']);
        self::assertSame(5000.0, $result['calculated']['combination_mass']);
        self::assertSame(350.0, $result['calculated']['vehicle_payload_remaining']);
    }

    public function testReportsTheOverallResultWhenAnyLimitIsExceeded(): void
    {
        $input = $this->validInput();
        $input['vehicle_mass_before_ball'] = 3000;
        $input['towball_mass'] = 250;

        $result = TowSmartCalculator::calculate($input);

        self::assertSame('exceeds_limit', $result['status']);
        self::assertSame('exceeds_limit', $result['checks'][0]['status']);
        self::assertSame(-150.0, $result['checks'][0]['remaining']);
    }

    public function testReportsNearLimitAtNinetyPercentUtilisation(): void
    {
        $input = $this->validInput();
        $input['trailer_loaded_mass'] = 2700;

        $result = TowSmartCalculator::calculate($input);

        self::assertSame('near_limit', $result['status']);
        self::assertSame('near_limit', $result['checks'][2]['status']);
    }

    public function testRejectsMissingOrNegativeValues(): void
    {
        $input = $this->validInput();
        unset($input['vehicle_gcm']);

        $this->expectException(InvalidArgumentException::class);
        TowSmartCalculator::calculate($input);
    }

    /** @return array<string,int> */
    private function validInput(): array
    {
        return [
            'vehicle_gvm' => 3100,
            'vehicle_gcm' => 6000,
            'vehicle_max_braked_towing' => 3000,
            'vehicle_max_towball' => 300,
            'vehicle_mass_before_ball' => 2500,
            'trailer_atm' => 3000,
            'trailer_loaded_mass' => 2500,
            'towball_mass' => 250,
        ];
    }
}
