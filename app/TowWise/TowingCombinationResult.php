<?php

declare(strict_types=1);

namespace App\TowWise;

final readonly class TowingCombinationResult
{
    /**
     * @param array<string,float> $marginsKg
     * @param array<int,string> $warnings
     * @param array<int,string> $assumptions
     */
    public function __construct(
        public string $status,
        public float $combinationMassKg,
        public float $vehicleMassIncludingTowballKg,
        public array $marginsKg,
        public array $warnings,
        public array $assumptions,
    ) {
    }

    public function withinKnownLimits(): bool
    {
        return $this->status === 'within_known_limits';
    }
}
