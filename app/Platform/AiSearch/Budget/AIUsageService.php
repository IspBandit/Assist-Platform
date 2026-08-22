<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Budget;

use App\Core\Database;
use Throwable;

/**
 * Records AI/orchestrator usage events and daily rollups.
 */
final class AIUsageService
{
    /**
     * @param array{
     *   request_id?:?string,
     *   brand_key?:string,
     *   operation_type:string,
     *   provider?:?string,
     *   model?:?string,
     *   input_tokens?:int,
     *   output_tokens?:int,
     *   cached?:bool,
     *   estimated_cost_aud?:float,
     *   actual_cost_aud?:?float,
     *   duration_ms?:?int,
     *   success?:bool,
     *   fallback_reason?:?string,
     *   assist_search_id?:?int,
     *   intent_confidence?:?float,
     *   budget_state?:?string
     * } $event
     */
    public function record(array $event): void
    {
        $operation = (string) $event['operation_type'];
        $brand = (string) ($event['brand_key'] ?? '');
        $cached = !empty($event['cached']);
        $success = array_key_exists('success', $event) ? (bool) $event['success'] : true;
        $cost = max(0.0, (float) ($event['estimated_cost_aud'] ?? 0));
        $provider = $event['provider'] ?? null;
        $budgetState = $event['budget_state'] ?? null;
        $isAi = is_string($provider) && $provider !== '' && $provider !== 'rules' && $provider !== 'cache' && $provider !== 'none';
        $budgetBlocked = $budgetState === AIBudgetService::STATE_HARD_STOP
            || $budgetState === AIBudgetService::STATE_AI_DISABLED
            || (($event['fallback_reason'] ?? null) === 'budget_blocked');

        try {
            $model = $event['model'] ?? null;
            $fallbackReason = $event['fallback_reason'] ?? null;
            $actualCost = $event['actual_cost_aud'] ?? null;

            Database::insert(
                'INSERT INTO ai_usage_events (
                    request_id, brand_key, operation_type, provider, model,
                    input_tokens, output_tokens, cached, estimated_cost_aud, actual_cost_aud,
                    duration_ms, success, fallback_reason, assist_search_id, intent_confidence,
                    budget_state, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $event['request_id'] ?? null,
                    mb_substr($brand, 0, 40),
                    mb_substr($operation, 0, 40),
                    is_string($provider) ? mb_substr($provider, 0, 40) : null,
                    is_string($model) ? mb_substr($model, 0, 80) : null,
                    max(0, (int) ($event['input_tokens'] ?? 0)),
                    max(0, (int) ($event['output_tokens'] ?? 0)),
                    $cached ? 1 : 0,
                    $cost,
                    is_float($actualCost) ? $actualCost : null,
                    $event['duration_ms'] ?? null,
                    $success ? 1 : 0,
                    is_string($fallbackReason) ? mb_substr($fallbackReason, 0, 120) : null,
                    $event['assist_search_id'] ?? null,
                    $event['intent_confidence'] ?? null,
                    is_string($budgetState) ? mb_substr($budgetState, 0, 40) : null,
                ]
            );

            Database::query(
                'INSERT INTO ai_usage_daily (
                    usage_date, brand_key, operation_type, requests, cache_hits, ai_requests,
                    rules_only, failed_requests, budget_blocked, estimated_cost_aud, updated_at
                ) VALUES (CURRENT_DATE, ?, ?, 1, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    requests = requests + 1,
                    cache_hits = cache_hits + VALUES(cache_hits),
                    ai_requests = ai_requests + VALUES(ai_requests),
                    rules_only = rules_only + VALUES(rules_only),
                    failed_requests = failed_requests + VALUES(failed_requests),
                    budget_blocked = budget_blocked + VALUES(budget_blocked),
                    estimated_cost_aud = estimated_cost_aud + VALUES(estimated_cost_aud),
                    updated_at = NOW()',
                [
                    mb_substr($brand, 0, 40),
                    mb_substr($operation, 0, 40),
                    $cached ? 1 : 0,
                    $isAi ? 1 : 0,
                    ($provider === 'rules') ? 1 : 0,
                    $success ? 0 : 1,
                    $budgetBlocked ? 1 : 0,
                    $cost,
                ]
            );
        } catch (Throwable) {
            // Observability must never break search.
        }
    }

    /**
     * Admin summary for today and current month.
     *
     * @return array<string,mixed>
     */
    public function adminSummary(): array
    {
        $budget = new AIBudgetService();
        $totals = $budget->usageTotals();
        $settings = AiSettings::get();

        $today = ['requests' => 0, 'cache_hits' => 0, 'ai_requests' => 0, 'rules_only' => 0, 'failed_requests' => 0, 'budget_blocked' => 0, 'estimated_cost_aud' => 0.0];
        $month = $today;

        try {
            $todayRow = Database::selectOne(
                'SELECT COALESCE(SUM(requests),0) AS requests,
                        COALESCE(SUM(cache_hits),0) AS cache_hits,
                        COALESCE(SUM(ai_requests),0) AS ai_requests,
                        COALESCE(SUM(rules_only),0) AS rules_only,
                        COALESCE(SUM(failed_requests),0) AS failed_requests,
                        COALESCE(SUM(budget_blocked),0) AS budget_blocked,
                        COALESCE(SUM(estimated_cost_aud),0) AS estimated_cost_aud
                 FROM ai_usage_daily WHERE usage_date = CURRENT_DATE'
            );
            $monthRow = Database::selectOne(
                'SELECT COALESCE(SUM(requests),0) AS requests,
                        COALESCE(SUM(cache_hits),0) AS cache_hits,
                        COALESCE(SUM(ai_requests),0) AS ai_requests,
                        COALESCE(SUM(rules_only),0) AS rules_only,
                        COALESCE(SUM(failed_requests),0) AS failed_requests,
                        COALESCE(SUM(budget_blocked),0) AS budget_blocked,
                        COALESCE(SUM(estimated_cost_aud),0) AS estimated_cost_aud
                 FROM ai_usage_daily WHERE usage_date >= DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\')'
            );
            if (is_array($todayRow)) {
                $today = array_merge($today, $todayRow);
            }
            if (is_array($monthRow)) {
                $month = array_merge($month, $monthRow);
            }
        } catch (Throwable) {
            // defaults
        }

        $todayRequests = max(0, (int) $today['requests']);
        $monthRequests = max(0, (int) $month['requests']);
        $cacheHitRateToday = $todayRequests > 0
            ? round(((int) $today['cache_hits'] / $todayRequests) * 100, 1)
            : 0.0;
        $rulesPctToday = $todayRequests > 0
            ? round(((int) $today['rules_only'] / $todayRequests) * 100, 1)
            : 0.0;
        $aiPctToday = $todayRequests > 0
            ? round(((int) $today['ai_requests'] / $todayRequests) * 100, 1)
            : 0.0;

        return [
            'settings' => $settings,
            'totals' => $totals,
            'today' => $today,
            'month' => $month,
            'cache_hit_rate_today_pct' => $cacheHitRateToday,
            'rules_resolved_today_pct' => $rulesPctToday,
            'ai_required_today_pct' => $aiPctToday,
            'budget_remaining_daily_aud' => $settings['daily_budget_aud'] > 0
                ? max(0.0, $settings['daily_budget_aud'] - (float) $totals['cost_today'])
                : null,
            'budget_remaining_monthly_aud' => $settings['monthly_budget_aud'] > 0
                ? max(0.0, $settings['monthly_budget_aud'] - (float) $totals['cost_month'])
                : null,
            'paid_ai_gate' => $budget->evaluatePaidAiAttempt(0.0),
        ];
    }
}
