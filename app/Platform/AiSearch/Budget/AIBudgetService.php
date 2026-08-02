<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Budget;

use App\Core\Database;
use Throwable;

/**
 * Hard-stop AI budget enforcement (ADR 0022).
 * Paid AI stays off until configured; rules/cache/local search always continue.
 */
final class AIBudgetService
{
    public const STATE_OK = 'ok';
    public const STATE_SOFT_WARN = 'soft_warn';
    public const STATE_HARD_STOP = 'hard_stop';
    public const STATE_AI_DISABLED = 'ai_disabled';
    public const STATE_PROVIDER_DISABLED = 'provider_disabled';

    /**
     * @param array{
     *   ai_enabled?:bool,
     *   openai_enabled?:bool,
     *   model_allowlist?:list<string>,
     *   daily_request_cap?:int,
     *   monthly_request_cap?:int,
     *   daily_budget_aud?:float,
     *   monthly_budget_aud?:float,
     *   soft_warn_pct?:int
     * }|null $settingsOverride
     * @param array{requests_today?:int,requests_month?:int,cost_today?:float,cost_month?:float}|null $usageOverride
     * @return array{
     *   allowed:bool,
     *   state:string,
     *   reason:?string,
     *   requests_today:int,
     *   requests_month:int,
     *   cost_today:float,
     *   cost_month:float,
     *   daily_request_cap:int,
     *   monthly_request_cap:int,
     *   daily_budget_aud:float,
     *   monthly_budget_aud:float
     * }
     */
    public function evaluatePaidAiAttempt(
        float $estimatedCostAud = 0.0,
        ?array $settingsOverride = null,
        ?array $usageOverride = null,
    ): array {
        $settings = array_merge(AiSettings::defaults(), $settingsOverride ?? AiSettings::get());
        $usage = array_merge([
            'requests_today' => 0,
            'requests_month' => 0,
            'cost_today' => 0.0,
            'cost_month' => 0.0,
        ], $usageOverride ?? $this->usageTotals());

        $base = [
            'allowed' => false,
            'state' => self::STATE_AI_DISABLED,
            'reason' => null,
            'requests_today' => $usage['requests_today'],
            'requests_month' => $usage['requests_month'],
            'cost_today' => $usage['cost_today'],
            'cost_month' => $usage['cost_month'],
            'daily_request_cap' => (int) $settings['daily_request_cap'],
            'monthly_request_cap' => (int) $settings['monthly_request_cap'],
            'daily_budget_aud' => (float) $settings['daily_budget_aud'],
            'monthly_budget_aud' => (float) $settings['monthly_budget_aud'],
        ];

        if (!$settings['ai_enabled']) {
            $base['reason'] = 'Global AI is disabled.';
            return $base;
        }

        if (!$settings['openai_enabled']) {
            $base['state'] = self::STATE_PROVIDER_DISABLED;
            $base['reason'] = 'OpenAI provider is disabled.';
            return $base;
        }

        if ($settings['model_allowlist'] === []) {
            $base['state'] = self::STATE_HARD_STOP;
            $base['reason'] = 'No models are allowlisted.';
            return $base;
        }

        // Zero caps = unpaid/unconfigured: never allow silent paid spend.
        if ((int) $settings['daily_request_cap'] <= 0
            && (int) $settings['monthly_request_cap'] <= 0
            && (float) $settings['daily_budget_aud'] <= 0
            && (float) $settings['monthly_budget_aud'] <= 0) {
            $base['state'] = self::STATE_HARD_STOP;
            $base['reason'] = 'No AI request or currency caps configured.';
            return $base;
        }

        if ((int) $settings['daily_request_cap'] > 0 && $usage['requests_today'] >= (int) $settings['daily_request_cap']) {
            $base['state'] = self::STATE_HARD_STOP;
            $base['reason'] = 'Daily AI request cap reached.';
            return $base;
        }

        if ((int) $settings['monthly_request_cap'] > 0 && $usage['requests_month'] >= (int) $settings['monthly_request_cap']) {
            $base['state'] = self::STATE_HARD_STOP;
            $base['reason'] = 'Monthly AI request cap reached.';
            return $base;
        }

        if ((float) $settings['daily_budget_aud'] > 0
            && ($usage['cost_today'] + $estimatedCostAud) > (float) $settings['daily_budget_aud']) {
            $base['state'] = self::STATE_HARD_STOP;
            $base['reason'] = 'Daily AI budget hard stop.';
            return $base;
        }

        if ((float) $settings['monthly_budget_aud'] > 0
            && ($usage['cost_month'] + $estimatedCostAud) > (float) $settings['monthly_budget_aud']) {
            $base['state'] = self::STATE_HARD_STOP;
            $base['reason'] = 'Monthly AI budget hard stop.';
            return $base;
        }

        $warn = $this->softWarn($settings, $usage, $estimatedCostAud);
        $base['allowed'] = true;
        $base['state'] = $warn ? self::STATE_SOFT_WARN : self::STATE_OK;
        $base['reason'] = $warn ? 'Soft warning threshold reached.' : null;
        return $base;
    }

    /**
     * @param array{
     *   ai_enabled:bool,
     *   openai_enabled:bool,
     *   model_allowlist:list<string>,
     *   daily_request_cap:int,
     *   monthly_request_cap:int,
     *   daily_budget_aud:float,
     *   monthly_budget_aud:float,
     *   soft_warn_pct:int,
     *   max_prompt_chars:int,
     *   max_output_tokens:int,
     *   max_retries:int,
     *   timeout_seconds:int,
     *   intent_cache_ttl_hours:int
     * } $settings
     * @param array{requests_today:int,requests_month:int,cost_today:float,cost_month:float} $usage
     */
    private function softWarn(array $settings, array $usage, float $estimatedCostAud): bool
    {
        $pct = max(1, min(100, $settings['soft_warn_pct'])) / 100.0;

        if ($settings['daily_request_cap'] > 0
            && $usage['requests_today'] >= (int) floor($settings['daily_request_cap'] * $pct)) {
            return true;
        }
        if ($settings['monthly_request_cap'] > 0
            && $usage['requests_month'] >= (int) floor($settings['monthly_request_cap'] * $pct)) {
            return true;
        }
        if ($settings['daily_budget_aud'] > 0
            && ($usage['cost_today'] + $estimatedCostAud) >= $settings['daily_budget_aud'] * $pct) {
            return true;
        }
        if ($settings['monthly_budget_aud'] > 0
            && ($usage['cost_month'] + $estimatedCostAud) >= $settings['monthly_budget_aud'] * $pct) {
            return true;
        }

        return false;
    }

    /**
     * @return array{requests_today:int,requests_month:int,cost_today:float,cost_month:float}
     */
    public function usageTotals(): array
    {
        $empty = [
            'requests_today' => 0,
            'requests_month' => 0,
            'cost_today' => 0.0,
            'cost_month' => 0.0,
        ];

        try {
            $today = Database::selectOne(
                'SELECT COALESCE(SUM(ai_requests),0) AS requests, COALESCE(SUM(estimated_cost_aud),0) AS cost
                 FROM ai_usage_daily WHERE usage_date = CURRENT_DATE'
            );
            $month = Database::selectOne(
                'SELECT COALESCE(SUM(ai_requests),0) AS requests, COALESCE(SUM(estimated_cost_aud),0) AS cost
                 FROM ai_usage_daily WHERE usage_date >= DATE_FORMAT(CURRENT_DATE, \'%Y-%m-01\')'
            );
        } catch (Throwable) {
            return $empty;
        }

        return [
            'requests_today' => (int) ($today['requests'] ?? 0),
            'requests_month' => (int) ($month['requests'] ?? 0),
            'cost_today' => (float) ($today['cost'] ?? 0),
            'cost_month' => (float) ($month['cost'] ?? 0),
        ];
    }
}
