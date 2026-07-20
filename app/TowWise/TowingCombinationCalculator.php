<?php

declare(strict_types=1);

namespace App\TowWise;

/**
 * Informational mass comparison only. This service deliberately contains no
 * jurisdiction-specific legal rules and does not certify a towing combination.
 */
final class TowingCombinationCalculator
{
    private const NEAR_LIMIT_PERCENT = 0.10;

    public function calculate(TowingCombinationInput $input): TowingCombinationResult
    {
        $vehicleWithTowball = $input->loadedVehicleKg + $input->actualTowballKg;
        $combinationMass = $vehicleWithTowball + $input->trailerGtmKg;

        $margins = [
            'vehicle_gvm' => $input->vehicleGvmKg - $vehicleWithTowball,
            'vehicle_gcm' => $input->vehicleGcmKg - $combinationMass,
            'braked_towing_capacity' => $input->maximumBrakedTowingKg - $input->trailerAtmKg,
            'towball_limit' => $input->maximumTowballKg - $input->actualTowballKg,
        ];

        $warnings = [];
        $nearLimit = false;
        $limits = [
            'vehicle_gvm' => $input->vehicleGvmKg,
            'vehicle_gcm' => $input->vehicleGcmKg,
            'braked_towing_capacity' => $input->maximumBrakedTowingKg,
            'towball_limit' => $input->maximumTowballKg,
        ];

        foreach ($margins as $key => $margin) {
            if ($margin < 0) {
                $warnings[] = $key . ' is exceeded by ' . self::formatKg(abs($margin)) . '.';
                continue;
            }
            if ($limits[$key] > 0 && $margin <= ($limits[$key] * self::NEAR_LIMIT_PERCENT)) {
                $nearLimit = true;
                $warnings[] = $key . ' has only ' . self::formatKg($margin) . ' remaining.';
            }
        }

        $status = array_filter($margins, static fn (float $margin): bool => $margin < 0) !== []
            ? 'exceeds_known_limit'
            : ($nearLimit ? 'near_known_limit' : 'within_known_limits');

        return new TowingCombinationResult(
            $status,
            $combinationMass,
            $vehicleWithTowball,
            $margins,
            $warnings,
            [
                'All values are user-entered or supplied by a separate verified data source.',
                'Loaded vehicle mass excludes towball download; towball download is added by this calculation.',
                'Combination mass is loaded vehicle mass plus towball download plus trailer GTM.',
                'ATM is compared with the vehicle maximum braked towing capacity.',
                'Actual axle loads, tyre limits, coupling limits and jurisdiction-specific rules are not assessed.',
                'Results are informational and are not legal certification or engineering approval.',
            ],
        );
    }

    private static function formatKg(float $value): string
    {
        return number_format($value, 1, '.', '') . ' kg';
    }
}
