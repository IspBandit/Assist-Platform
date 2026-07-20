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
        $actualTrailerMass = $input->actualTrailerMass();
        $actualTrailerAxle = $input->actualTrailerAxleMass();
        $combinationMass = $vehicleWithTowball + $actualTrailerAxle;

        $definitions = [
            'vehicle_gvm' => [$vehicleWithTowball, $input->vehicleGvmKg],
            'vehicle_gcm' => [$combinationMass, $input->vehicleGcmKg],
            'braked_towing_capacity' => [$actualTrailerMass, $input->maximumBrakedTowingKg],
            'trailer_atm' => [$actualTrailerMass, $input->trailerAtmKg],
            'trailer_gtm' => [$actualTrailerAxle, $input->trailerGtmKg],
            'towball_limit' => [$input->actualTowballKg, $input->maximumTowballKg],
        ];
        if ($input->towbarLimitKg !== null) $definitions['towbar_rating'] = [$actualTrailerMass, $input->towbarLimitKg];
        if ($input->couplingLimitKg !== null) $definitions['coupling_rating'] = [$actualTrailerMass, $input->couplingLimitKg];
        if ($input->frontAxleLimitKg !== null && $input->actualFrontAxleKg !== null) $definitions['front_axle'] = [$input->actualFrontAxleKg, $input->frontAxleLimitKg];
        if ($input->rearAxleLimitKg !== null && $input->actualRearAxleKg !== null) $definitions['rear_axle'] = [$input->actualRearAxleKg, $input->rearAxleLimitKg];
        if ($input->trailerAxleLimitKg !== null) $definitions['trailer_axle_group'] = [$actualTrailerAxle, $input->trailerAxleLimitKg];

        $margins = [];
        $checks = [];
        foreach ($definitions as $key => [$actual, $limit]) {
            $margin = $limit - $actual;
            $margins[$key] = $margin;
            $checks[$key] = ['actual' => $actual, 'limit' => $limit, 'margin' => $margin, 'status' => $margin < 0 ? 'exceeds' : ($limit > 0 && $margin <= $limit * self::NEAR_LIMIT_PERCENT ? 'near' : 'within')];
        }

        $warnings = [];
        $nearLimit = false;
        foreach ($checks as $key => $check) {
            $margin = $check['margin'];
            if ($margin < 0) {
                $warnings[] = $key . ' is exceeded by ' . self::formatKg(abs($margin)) . '.';
                continue;
            }
            if ($check['status'] === 'near') {
                $nearLimit = true;
                $warnings[] = $key . ' has only ' . self::formatKg($margin) . ' remaining.';
            }
        }

        $status = array_filter($margins, static fn (float $margin): bool => $margin < 0) !== []
            ? 'likely_exceeds_entered_limit'
            : ($nearLimit ? 'close_to_entered_limit' : 'within_entered_limits');

        $missing = [];
        foreach (['towbarLimitKg' => 'Towbar rating', 'couplingLimitKg' => 'Trailer coupling rating', 'frontAxleLimitKg' => 'Front axle measurement and limit', 'rearAxleLimitKg' => 'Rear axle measurement and limit', 'trailerAxleLimitKg' => 'Trailer axle-group rating'] as $property => $label) {
            if ($input->{$property} === null) $missing[] = $label;
        }

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
                'Tyre and wheel ratings, dimensions, registration, modifications and jurisdiction-specific road rules are not assessed.',
                'The result estimates whether the combination is likely to be within the limits entered; it is not a legal determination.',
            ],
            $checks,
            $missing,
            $missing === [] ? 'high_for_entered_mass_limits' : (count($missing) <= 2 ? 'medium' : 'basic'),
        );
    }

    private static function formatKg(float $value): string
    {
        return number_format($value, 1, '.', '') . ' kg';
    }
}
