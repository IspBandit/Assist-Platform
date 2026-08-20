<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Budget;

/**
 * What-if cost simulator for release planning (AI-7). Does not call vendors.
 */
final class AiCostSimulator
{
    /**
     * @return array{
     *   model:string,
     *   searches_per_day:int,
     *   ai_hit_rate:float,
     *   estimated_input_tokens:int,
     *   max_output_tokens:int,
     *   cost_per_ai_call_aud:float,
     *   daily_ai_calls:int,
     *   daily_aud:float,
     *   monthly_aud:float,
     *   notes:list<string>
     * }
     */
    public static function simulate(
        string $model,
        int $searchesPerDay,
        float $aiHitRatePct,
        ?int $approxInputTokens = null,
        ?int $maxOutputTokens = null,
    ): array {
        $searchesPerDay = max(0, min(1_000_000, $searchesPerDay));
        $aiHitRate = max(0.0, min(100.0, $aiHitRatePct)) / 100.0;
        $inputTokens = $approxInputTokens ?? (int) config('ai_search.cost_sim_default_input_tokens', 800);
        $outputTokens = $maxOutputTokens ?? (int) config('ai_search.cost_sim_default_output_tokens', 500);
        $inputTokens = max(1, min(50_000, $inputTokens));
        $outputTokens = max(32, min(8_000, $outputTokens));

        $model = trim($model);
        if ($model === '') {
            $model = 'gpt-4o-mini';
        }

        $perCall = AiCostEstimator::estimateAud($model, $inputTokens, $outputTokens);
        $dailyAiCalls = (int) round($searchesPerDay * $aiHitRate);
        $daily = round($dailyAiCalls * $perCall, 4);
        $monthly = round($daily * 30, 4);

        return [
            'model' => $model,
            'searches_per_day' => $searchesPerDay,
            'ai_hit_rate' => round($aiHitRate * 100, 1),
            'estimated_input_tokens' => $inputTokens,
            'max_output_tokens' => $outputTokens,
            'cost_per_ai_call_aud' => $perCall,
            'daily_ai_calls' => $dailyAiCalls,
            'daily_aud' => $daily,
            'monthly_aud' => $monthly,
            'notes' => [
                'Simulation only — not a billing forecast.',
                'Rules/cache hits cost $0 paid AI.',
                'Zero allowlist or disabled AI ⇒ actual paid spend remains $0.',
            ],
        ];
    }
}
