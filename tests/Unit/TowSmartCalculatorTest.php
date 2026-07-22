<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TowSmartCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TowSmartCalculatorTest extends TestCase
{
    public function testDetailedTowWiseStyleLoadConfigurationIsDerived(): void
    {
        $result = TowSmartCalculator::calculate([
            'vehicle_kerb_mass' => 2200, 'vehicle_gvm' => 3200, 'vehicle_gcm' => 6500,
            'vehicle_max_braked_towing' => 3500, 'vehicle_max_towball' => 350,
            'passengers_mass' => 160, 'vehicle_cargo_mass' => 100, 'vehicle_accessories_mass' => 80, 'fuel_mass' => 0,
            'trailer_tare_mass' => 2400, 'trailer_atm' => 3200, 'trailer_tare_ball_mass' => 220,
            'trailer_cargo_mass' => 200, 'trailer_accessories_mass' => 50,
            'trailer_front_accessories_mass' => 20, 'trailer_rear_accessories_mass' => 20,
            'tank_1_litres' => 100, 'tank_1_position' => 'front', 'tank_2_litres' => 0, 'tank_2_position' => 'middle',
        ]);

        self::assertSame(2815.0, $result['calculated']['vehicle_loaded_mass']);
        self::assertSame(2515.0, $result['calculated']['trailer_gtm']);
        self::assertSame(5330.0, $result['calculated']['combination_mass']);
    }

    public function testCalculatesACombinationWithinAllKnownLimits(): void
    {
        $result = TowSmartCalculator::calculate($this->validInput());

        self::assertSame('within_limits', $result['status']);
        self::assertSame(2750.0, $result['calculated']['vehicle_loaded_mass']);
        self::assertSame(2250.0, $result['calculated']['trailer_gtm']);
        self::assertSame(5000.0, $result['calculated']['combination_mass']);
        self::assertSame(350.0, $result['calculated']['vehicle_payload_remaining']);
        self::assertSame(10.0, $result['calculated']['towball_percentage']);
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

    public function testRejectsImpossibleTowballAndZeroRatedLimits(): void
    {
        $input = $this->validInput();
        $input['towball_mass'] = 2600;

        try {
            TowSmartCalculator::calculate($input);
            self::fail('Impossible towball mass was accepted');
        } catch (InvalidArgumentException $e) {
            self::assertStringContainsString('Towball mass', $e->getMessage());
        }

        $input = $this->validInput();
        $input['vehicle_gvm'] = 0;
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
