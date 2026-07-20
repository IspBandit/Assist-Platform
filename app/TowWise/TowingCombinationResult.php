<?php

declare(strict_types=1);

namespace App\TowWise;

final readonly class TowingCombinationResult
{
    /**
     * @param array<string,float> $marginsKg
     * @param array<int,string> $warnings
     * @param array<int,string> $assumptions
     * @param array<string,array{actual:float,limit:float,margin:float,status:string}> $checks
     * @param array<int,string> $missingChecks
     */
    public function __construct(
        public string $status,
        public float $combinationMassKg,
        public float $vehicleMassIncludingTowballKg,
        public array $marginsKg,
        public array $warnings,
        public array $assumptions,
        public array $checks,
        public array $missingChecks,
        public string $confidence,
    ) {
    }

    public function withinKnownLimits(): bool
    {
        return $this->status === 'within_entered_limits';
    }
}
