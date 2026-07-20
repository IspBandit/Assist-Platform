<?php

declare(strict_types=1);

namespace App\TowSmart;

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
        public ?float $actualTrailerMassKg = null,
        public ?float $towbarLimitKg = null,
        public ?float $couplingLimitKg = null,
        public ?float $frontAxleLimitKg = null,
        public ?float $actualFrontAxleKg = null,
        public ?float $rearAxleLimitKg = null,
        public ?float $actualRearAxleKg = null,
        public ?float $trailerAxleLimitKg = null,
        public ?float $actualTrailerAxleKg = null,
    ) {
        foreach (get_object_vars($this) as $field => $value) {
            if ($value !== null && (!is_finite($value) || $value < 0)) {
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

        foreach ([['frontAxleLimitKg', 'actualFrontAxleKg'], ['rearAxleLimitKg', 'actualRearAxleKg']] as [$limit, $actual]) {
            if (($this->{$limit} === null) !== ($this->{$actual} === null)) {
                throw new InvalidArgumentException("{$limit} and {$actual} must be entered together");
            }
        }

        if ($this->actualTrailerMass() < $this->actualTowballKg) {
            throw new InvalidArgumentException('Actual trailer mass cannot be less than actual towball mass');
        }
    }

    public function actualTrailerMass(): float
    {
        return $this->actualTrailerMassKg ?? $this->trailerAtmKg;
    }

    public function actualTrailerAxleMass(): float
    {
        return $this->actualTrailerAxleKg ?? ($this->actualTrailerMass() - $this->actualTowballKg);
    }
}
