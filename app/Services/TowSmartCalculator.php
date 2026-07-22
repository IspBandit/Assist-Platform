<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Deterministic mass-limit checks for one loaded tow combination.
 *
 * All values are kilograms. This is informational calculation only: it does
 * not replace manufacturer specifications, weighing, engineering, or legal
 * advice. Vehicle mass is supplied before towball download is applied.
 */
final class TowSmartCalculator
{
    private const REQUIRED = [
        'vehicle_gvm', 'vehicle_gcm', 'vehicle_max_braked_towing',
        'vehicle_max_towball', 'vehicle_mass_before_ball',
        'trailer_atm', 'trailer_loaded_mass', 'towball_mass',
    ];

    /** @param array<string,int|float|string> $input @return array<string,mixed> */
    public static function calculate(array $input): array
    {
        $values = [];
        foreach (self::REQUIRED as $key) {
            if (!array_key_exists($key, $input) || !is_numeric($input[$key])) {
                throw new InvalidArgumentException("Missing or invalid towing value: {$key}");
            }
            $values[$key] = (float) $input[$key];
            if ($values[$key] < 0) {
                throw new InvalidArgumentException("Towing values cannot be negative: {$key}");
            }
        }

        foreach (['vehicle_gvm', 'vehicle_gcm', 'vehicle_max_braked_towing', 'vehicle_max_towball', 'trailer_atm'] as $limit) {
            if ($values[$limit] <= 0) {
                throw new InvalidArgumentException("Rated limits must be greater than zero: {$limit}");
            }
        }
        if ($values['trailer_loaded_mass'] <= 0) {
            throw new InvalidArgumentException('Actual loaded trailer mass must be greater than zero');
        }
        if ($values['towball_mass'] > $values['trailer_loaded_mass']) {
            throw new InvalidArgumentException('Towball mass cannot exceed the actual loaded trailer mass');
        }

        $vehicleLoaded = $values['vehicle_mass_before_ball'] + $values['towball_mass'];
        $trailerGtm = $values['trailer_loaded_mass'] - $values['towball_mass'];
        $combinationMass = $values['vehicle_mass_before_ball'] + $values['trailer_loaded_mass'];

        $checks = [
            self::check('vehicle_gvm', 'Loaded vehicle mass', $vehicleLoaded, $values['vehicle_gvm']),
            self::check('vehicle_gcm', 'Combined mass', $combinationMass, $values['vehicle_gcm']),
            self::check('braked_towing', 'Loaded trailer mass', $values['trailer_loaded_mass'], $values['vehicle_max_braked_towing']),
            self::check('trailer_atm', 'Loaded trailer mass', $values['trailer_loaded_mass'], $values['trailer_atm']),
            self::check('towball', 'Towball mass', $values['towball_mass'], $values['vehicle_max_towball']),
        ];

        $status = 'within_limits';
        foreach ($checks as $check) {
            if ($check['status'] === 'exceeds_limit') {
                $status = 'exceeds_limit';
                break;
            }
            if ($check['status'] === 'near_limit') {
                $status = 'near_limit';
            }
        }

        return [
            'status' => $status,
            'calculated' => [
                'vehicle_loaded_mass' => $vehicleLoaded,
                'trailer_gtm' => $trailerGtm,
                'combination_mass' => $combinationMass,
                'vehicle_payload_remaining' => $values['vehicle_gvm'] - $vehicleLoaded,
                'trailer_payload_remaining' => $values['trailer_atm'] - $values['trailer_loaded_mass'],
                'towball_percentage' => round(($values['towball_mass'] / $values['trailer_loaded_mass']) * 100, 1),
            ],
            'checks' => $checks,
            'disclaimer' => 'Informational estimate only. Confirm the exact vehicle and trailer specifications and obtain actual loaded weights. Requirements can vary by jurisdiction and modification.',
        ];
    }

    /** @return array{key:string,label:string,actual:float,limit:float,remaining:float,utilisation_percent:float,status:string} */
    private static function check(string $key, string $label, float $actual, float $limit): array
    {
        $remaining = $limit - $actual;
        $utilisation = $limit > 0 ? ($actual / $limit) * 100 : 100.0;
        $status = $remaining < 0 ? 'exceeds_limit' : ($utilisation >= 90 ? 'near_limit' : 'within_limit');

        return [
            'key' => $key,
            'label' => $label,
            'actual' => round($actual, 1),
            'limit' => round($limit, 1),
            'remaining' => round($remaining, 1),
            'utilisation_percent' => round($utilisation, 1),
            'status' => $status,
        ];
    }
}
