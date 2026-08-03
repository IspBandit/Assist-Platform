<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Services\TowSmartCalculator;
use App\Services\TowSmartCatalog;
use InvalidArgumentException;
use Throwable;

/**
 * Polaris → TowSmart compatibility boundary.
 * Does not duplicate vehicle catalogue data; consumes TowSmart services only.
 */
final class TowCompatibilityService
{
    public const RESULT_COMPATIBLE = 'compatible';
    public const RESULT_POTENTIALLY = 'potentially_compatible';
    public const RESULT_INCOMPATIBLE = 'incompatible';
    public const RESULT_INSUFFICIENT = 'insufficient_data';

    /**
     * @param array<string,mixed> $vehicle TowSmart catalogue vehicle row or manual figures
     * @param array<string,mixed> $rv Variant or aggregate with tare_kg / atm_kg / towball_mass_kg
     * @return array<string,mixed>
     */
    public function assess(array $vehicle, array $rv, array $assumptions = []): array
    {
        $vehicleFigures = $this->normaliseVehicle($vehicle);
        $rvAtm = isset($rv['atm_kg']) && is_numeric($rv['atm_kg']) ? (int) $rv['atm_kg'] : null;
        $rvTare = isset($rv['tare_kg']) && is_numeric($rv['tare_kg']) ? (int) $rv['tare_kg'] : null;
        $towball = isset($rv['towball_mass_kg']) && is_numeric($rv['towball_mass_kg'])
            ? (int) $rv['towball_mass_kg']
            : (isset($assumptions['towball_mass']) ? (int) $assumptions['towball_mass'] : null);

        $missing = [];
        foreach (['gvm', 'gcm', 'max_braked_towing', 'max_towball', 'mass_before_ball'] as $key) {
            if (($vehicleFigures[$key] ?? null) === null) {
                $missing[] = "Vehicle {$key} is unavailable.";
            }
        }
        if ($rvAtm === null) {
            $missing[] = 'RV ATM is unavailable.';
        }
        if ($rvTare === null) {
            $missing[] = 'RV tare is unavailable.';
        }
        if ($towball === null) {
            // Guidance default: 10% of tare when manufacturer towball is unknown.
            if ($rvTare !== null) {
                $towball = (int) round($rvTare * 0.10);
                $assumptions['towball_assumed'] = true;
                $assumptions['towball_mass'] = $towball;
            } else {
                $missing[] = 'Towball mass is unavailable.';
            }
        }

        if ($missing !== []) {
            return [
                'status' => self::RESULT_INSUFFICIENT,
                'headline' => 'Insufficient data for a compatibility check.',
                'summary' => 'Based on the figures supplied, key limits cannot be checked. Confirm manufacturer plates and weigh the combination before travel.',
                'missing' => $missing,
                'assumptions' => $assumptions,
                'warnings' => ['Do not treat an incomplete check as approval to tow.'],
                'confidence' => 'low',
                'calculation' => null,
            ];
        }

        $loadedTrailer = isset($assumptions['trailer_loaded_mass'])
            ? (int) $assumptions['trailer_loaded_mass']
            : (int) round(((int) $rvTare + (int) $rvAtm) / 2);

        try {
            $calc = TowSmartCalculator::calculate([
                'vehicle_gvm' => (float) $vehicleFigures['gvm'],
                'vehicle_gcm' => (float) $vehicleFigures['gcm'],
                'vehicle_max_braked_towing' => (float) $vehicleFigures['max_braked_towing'],
                'vehicle_max_towball' => (float) $vehicleFigures['max_towball'],
                'vehicle_mass_before_ball' => (float) $vehicleFigures['mass_before_ball'],
                'trailer_atm' => (float) $rvAtm,
                'trailer_loaded_mass' => (float) $loadedTrailer,
                'towball_mass' => (float) $towball,
            ]);
        } catch (InvalidArgumentException | Throwable $e) {
            return [
                'status' => self::RESULT_INSUFFICIENT,
                'headline' => 'Compatibility could not be calculated.',
                'summary' => $e->getMessage(),
                'missing' => $missing,
                'assumptions' => $assumptions,
                'warnings' => [],
                'confidence' => 'low',
                'calculation' => null,
            ];
        }

        $status = match ($calc['status']) {
            'within_limits' => self::RESULT_COMPATIBLE,
            'near_limit' => self::RESULT_POTENTIALLY,
            default => self::RESULT_INCOMPATIBLE,
        };

        $limiting = null;
        foreach ($calc['checks'] as $check) {
            if (($check['status'] ?? '') === 'exceeds_limit') {
                $limiting = (string) ($check['label'] ?? $check['key'] ?? 'limit');
                break;
            }
        }

        $headline = match ($status) {
            self::RESULT_COMPATIBLE => 'Appears within the checked limits (assumptions apply).',
            self::RESULT_POTENTIALLY => 'Near one or more limits — review carefully.',
            default => 'Exceeds one or more checked limits under these assumptions.',
        };

        return [
            'status' => $status,
            'headline' => $headline,
            'summary' => 'Based on the figures and assumptions supplied, this combination '
                . ($status === self::RESULT_COMPATIBLE
                    ? 'appears to remain within the checked limits. Confirm actual loaded weights and applicable requirements before travel.'
                    : 'needs review against actual loaded weights and applicable requirements before travel.'),
            'missing' => [],
            'assumptions' => $assumptions + [
                'trailer_loaded_mass' => $loadedTrailer,
                'towball_mass' => $towball,
            ],
            'warnings' => array_values(array_filter([
                !empty($assumptions['towball_assumed'])
                    ? 'Towball mass was assumed at about 10% of tare because a manufacturer figure was not available.'
                    : null,
                'Informational guidance only — not a legal or safety certification.',
                $limiting !== null ? "Limiting factor: {$limiting}." : null,
            ])),
            'limiting_factor' => $limiting,
            'confidence' => $status === self::RESULT_COMPATIBLE ? 'medium' : 'medium',
            'calculation' => $calc,
        ];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function searchVehicles(string $query, int $limit = 12): array
    {
        return TowSmartCatalog::search('vehicles', $query, $limit);
    }

    /** @return array<string,mixed>|null */
    public function findVehicle(int $id): ?array
    {
        return TowSmartCatalog::find('vehicles', $id);
    }

    /**
     * @param array<string,mixed> $vehicle
     * @return array{gvm:?float,gcm:?float,max_braked_towing:?float,max_towball:?float,mass_before_ball:?float,label:string}
     */
    private function normaliseVehicle(array $vehicle): array
    {
        $num = static function (array $row, array $keys): ?float {
            foreach ($keys as $key) {
                if (isset($row[$key]) && is_numeric($row[$key]) && (float) $row[$key] > 0) {
                    return (float) $row[$key];
                }
            }
            return null;
        };

        $kerb = $num($vehicle, ['kerb_weight', 'kerb', 'kerb_mass', 'tare', 'vehicle_kerb_mass', 'mass_before_ball', 'vehicle_mass_before_ball']);
        $gvm = $num($vehicle, ['gvm', 'vehicle_gvm']);
        $passengers = isset($vehicle['passengers_mass']) && is_numeric($vehicle['passengers_mass'])
            ? (float) $vehicle['passengers_mass']
            : 150.0;

        $massBeforeBall = $num($vehicle, ['vehicle_mass_before_ball', 'mass_before_ball']);
        if ($massBeforeBall === null && $kerb !== null) {
            $massBeforeBall = $kerb + $passengers;
        }

        $label = trim(implode(' ', array_filter([
            (string) ($vehicle['brand'] ?? $vehicle['make'] ?? ''),
            (string) ($vehicle['name'] ?? $vehicle['model'] ?? ''),
            (string) ($vehicle['years'] ?? $vehicle['year'] ?? ''),
        ])));

        return [
            'gvm' => $gvm,
            'gcm' => $num($vehicle, ['gcm', 'vehicle_gcm']),
            'max_braked_towing' => $num($vehicle, [
                'towing_capacity', 'braked', 'max_braked', 'max_braked_towing',
                'vehicle_max_braked_towing', 'tow_capacity',
            ]),
            'max_towball' => $num($vehicle, [
                'towball_download_max', 'towball', 'max_towball',
                'vehicle_max_towball', 'ball_weight',
            ]),
            'mass_before_ball' => $massBeforeBall,
            'label' => $label !== '' ? $label : 'Selected tow vehicle',
        ];
    }
}
