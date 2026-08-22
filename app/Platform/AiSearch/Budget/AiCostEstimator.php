<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Budget;

/**
 * Estimates AUD cost for budget pre-checks. Rates are config-driven — not a
 * hard-coded choice of which model to call.
 */
final class AiCostEstimator
{
    /**
     * Conservative pre-call estimate using max configured output tokens.
     */
    public static function estimateAud(string $model, int $approxInputTokens, int $maxOutputTokens): float
    {
        $rates = self::ratesFor($model);
        $input = max(0, $approxInputTokens) / 1_000_000.0 * $rates['input'];
        $output = max(0, $maxOutputTokens) / 1_000_000.0 * $rates['output'];
        // Convert USD list price to AUD using configurable FX (conservative).
        $usdToAud = (float) config('ai_search.usd_to_aud', 1.6);
        return round(($input + $output) * max(0.5, $usdToAud), 6);
    }

    public static function fromUsage(string $model, int $inputTokens, int $outputTokens): float
    {
        $rates = self::ratesFor($model);
        $usd = ($inputTokens / 1_000_000.0) * $rates['input']
            + ($outputTokens / 1_000_000.0) * $rates['output'];
        $usdToAud = (float) config('ai_search.usd_to_aud', 1.6);
        return round($usd * max(0.5, $usdToAud), 6);
    }

    /**
     * @return array{input:float,output:float}
     */
    private static function ratesFor(string $model): array
    {
        /** @var array<string,array{input?:float|int,output?:float|int}> $map */
        $map = (array) config('ai_search.model_cost_usd_per_1m', []);
        $needle = mb_strtolower(trim($model));
        foreach ($map as $prefix => $rates) {
            if ($prefix !== '' && str_starts_with($needle, mb_strtolower((string) $prefix))) {
                return [
                    'input' => (float) ($rates['input'] ?? 0.15),
                    'output' => (float) ($rates['output'] ?? 0.60),
                ];
            }
        }
        // Unknown model: use gpt-4o-mini-class rates as a conservative default for budgeting.
        return ['input' => 0.15, 'output' => 0.60];
    }
}
