<?php
declare(strict_types=1);
namespace App\Platform\DataIntelligence;

final class OpportunityScorer
{
    /** @param array<string,int|float|null> $m */
    public static function score(array $m): float
    {
        $providers = max(0, (int) ($m['providers'] ?? 0));
        $verified = max(0, (int) ($m['verified'] ?? 0));
        $population = max(0, (int) ($m['population'] ?? 0));
        $demand = max(0, (int) ($m['demand'] ?? 0));
        $zeroResults = max(0, (int) ($m['zero_results'] ?? 0));

        $supplyGap = $providers === 0 ? 45.0 : max(0.0, 30.0 - min(30.0, $providers * 4.0));
        $populationPressure = $population > 0 ? min(25.0, ($population / max(1, $providers)) / 2000.0) : 0.0;
        $demandPressure = min(20.0, ($demand * 2.0) + ($zeroResults * 3.0));
        $verificationGap = $providers > 0 ? (1.0 - min(1.0, $verified / $providers)) * 10.0 : 0.0;
        return round(min(100.0, $supplyGap + $populationPressure + $demandPressure + $verificationGap), 2);
    }

    public static function priority(float $score): string
    {
        return $score >= 80 ? 'critical' : ($score >= 60 ? 'high' : ($score >= 35 ? 'medium' : 'low'));
    }
}
