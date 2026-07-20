<?php

declare(strict_types=1);

namespace App\TowWise;

use InvalidArgumentException;

final readonly class TowingCombinationInput
{
    public function __construct(
        public float $vehicleGvmKg,
        public float $vehicleGcmKg,
        public float $maximumBrakedTowingKg,
        public float $maximumTowballKg,
        public float $loadedVehicleKg,
        public float $trailerAtmKg,
        public float $trailerGtmKg,
        public float $actualTowballKg,
    ) {
        foreach (get_object_vars($this) as $field => $value) {
            if (!is_finite($value) || $value < 0) {
                throw new InvalidArgumentException("{$field} must be a finite, non-negative kilogram value");
            }
        }

        foreach (['vehicleGvmKg', 'vehicleGcmKg', 'maximumBrakedTowingKg', 'loadedVehicleKg', 'trailerAtmKg'] as $field) {
            if ($this->{$field} <= 0) {
                throw new InvalidArgumentException("{$field} must be greater than zero");
            }
        }

        if ($this->trailerGtmKg > $this->trailerAtmKg) {
            throw new InvalidArgumentException('Trailer GTM cannot exceed trailer ATM');
        }
    }
}
