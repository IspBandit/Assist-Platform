<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Budget;

use App\Core\Database;
use Throwable;

/**
 * Loads AI cost-control settings. Secrets never stored here.
 *
 * @phpstan-type AiSettingsArray array{
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
 * }
 */
final class AiSettings
{
    /** @var AiSettingsArray|null */
    private static ?array $cache = null;

    /** @return AiSettingsArray */
    public static function defaults(): array
    {
        return [
            'ai_enabled' => false,
            'openai_enabled' => false,
            'model_allowlist' => [],
            'daily_request_cap' => 0,
            'monthly_request_cap' => 0,
            'daily_budget_aud' => 0.0,
            'monthly_budget_aud' => 0.0,
            'soft_warn_pct' => 80,
            'max_prompt_chars' => 2000,
            'max_output_tokens' => 500,
            'max_retries' => 1,
            'timeout_seconds' => 15,
            'intent_cache_ttl_hours' => (int) config('ai_search.intent_cache_ttl_hours', 168),
        ];
    }

    /** @return AiSettingsArray */
    public static function get(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        try {
            $row = Database::selectOne('SELECT * FROM ai_settings WHERE id = 1');
        } catch (Throwable) {
            $row = null;
        }

        if ($row === null) {
            self::$cache = self::defaults();
            return self::$cache;
        }

        $allowlist = [];
        if (isset($row['model_allowlist_json']) && is_string($row['model_allowlist_json']) && $row['model_allowlist_json'] !== '') {
            $decoded = json_decode($row['model_allowlist_json'], true);
            if (is_array($decoded)) {
                foreach ($decoded as $item) {
                    if (is_string($item) && $item !== '') {
                        $allowlist[] = $item;
                    }
                }
            }
        }

        self::$cache = [
            'ai_enabled' => (int) ($row['ai_enabled'] ?? 0) === 1,
            'openai_enabled' => (int) ($row['openai_enabled'] ?? 0) === 1,
            'model_allowlist' => $allowlist,
            'daily_request_cap' => max(0, (int) ($row['daily_request_cap'] ?? 0)),
            'monthly_request_cap' => max(0, (int) ($row['monthly_request_cap'] ?? 0)),
            'daily_budget_aud' => max(0.0, (float) ($row['daily_budget_aud'] ?? 0)),
            'monthly_budget_aud' => max(0.0, (float) ($row['monthly_budget_aud'] ?? 0)),
            'soft_warn_pct' => max(1, min(100, (int) ($row['soft_warn_pct'] ?? 80))),
            'max_prompt_chars' => max(100, (int) ($row['max_prompt_chars'] ?? 2000)),
            'max_output_tokens' => max(32, (int) ($row['max_output_tokens'] ?? 500)),
            'max_retries' => max(0, min(5, (int) ($row['max_retries'] ?? 1))),
            'timeout_seconds' => max(1, min(120, (int) ($row['timeout_seconds'] ?? 15))),
            'intent_cache_ttl_hours' => max(1, (int) ($row['intent_cache_ttl_hours'] ?? 168)),
        ];

        return self::$cache;
    }

    /**
     * @param array<string,mixed> $input
     * @return AiSettingsArray
     */
    public static function save(array $input, ?int $userId): array
    {
        $current = self::get();
        $allowlistRaw = $input['model_allowlist'] ?? $current['model_allowlist'];
        $allowlist = [];
        if (is_string($allowlistRaw)) {
            foreach (preg_split('/[\s,]+/', $allowlistRaw) ?: [] as $part) {
                $part = trim($part);
                if ($part !== '') {
                    $allowlist[] = $part;
                }
            }
        } elseif (is_array($allowlistRaw)) {
            foreach ($allowlistRaw as $part) {
                if (is_string($part) && $part !== '') {
                    $allowlist[] = $part;
                }
            }
        }

        $settings = [
            'ai_enabled' => !empty($input['ai_enabled']),
            'openai_enabled' => !empty($input['openai_enabled']),
            'model_allowlist' => array_values(array_unique($allowlist)),
            'daily_request_cap' => max(0, (int) ($input['daily_request_cap'] ?? $current['daily_request_cap'])),
            'monthly_request_cap' => max(0, (int) ($input['monthly_request_cap'] ?? $current['monthly_request_cap'])),
            'daily_budget_aud' => max(0.0, (float) ($input['daily_budget_aud'] ?? $current['daily_budget_aud'])),
            'monthly_budget_aud' => max(0.0, (float) ($input['monthly_budget_aud'] ?? $current['monthly_budget_aud'])),
            'soft_warn_pct' => max(1, min(100, (int) ($input['soft_warn_pct'] ?? $current['soft_warn_pct']))),
            'max_prompt_chars' => max(100, (int) ($input['max_prompt_chars'] ?? $current['max_prompt_chars'])),
            'max_output_tokens' => max(32, (int) ($input['max_output_tokens'] ?? $current['max_output_tokens'])),
            'max_retries' => max(0, min(5, (int) ($input['max_retries'] ?? $current['max_retries']))),
            'timeout_seconds' => max(1, min(120, (int) ($input['timeout_seconds'] ?? $current['timeout_seconds']))),
            'intent_cache_ttl_hours' => max(1, (int) ($input['intent_cache_ttl_hours'] ?? $current['intent_cache_ttl_hours'])),
        ];

        // Hard safety: never enable OpenAI without global AI; never auto-upgrade models.
        if (!$settings['ai_enabled']) {
            $settings['openai_enabled'] = false;
        }

        Database::query(
            'INSERT INTO ai_settings (
                id, ai_enabled, openai_enabled, model_allowlist_json,
                daily_request_cap, monthly_request_cap, daily_budget_aud, monthly_budget_aud,
                soft_warn_pct, max_prompt_chars, max_output_tokens, max_retries, timeout_seconds,
                intent_cache_ttl_hours, updated_at, updated_by
            ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
            ON DUPLICATE KEY UPDATE
                ai_enabled=VALUES(ai_enabled),
                openai_enabled=VALUES(openai_enabled),
                model_allowlist_json=VALUES(model_allowlist_json),
                daily_request_cap=VALUES(daily_request_cap),
                monthly_request_cap=VALUES(monthly_request_cap),
                daily_budget_aud=VALUES(daily_budget_aud),
                monthly_budget_aud=VALUES(monthly_budget_aud),
                soft_warn_pct=VALUES(soft_warn_pct),
                max_prompt_chars=VALUES(max_prompt_chars),
                max_output_tokens=VALUES(max_output_tokens),
                max_retries=VALUES(max_retries),
                timeout_seconds=VALUES(timeout_seconds),
                intent_cache_ttl_hours=VALUES(intent_cache_ttl_hours),
                updated_at=NOW(),
                updated_by=VALUES(updated_by)',
            [
                $settings['ai_enabled'] ? 1 : 0,
                $settings['openai_enabled'] ? 1 : 0,
                json_encode($settings['model_allowlist'], JSON_THROW_ON_ERROR),
                $settings['daily_request_cap'],
                $settings['monthly_request_cap'],
                $settings['daily_budget_aud'],
                $settings['monthly_budget_aud'],
                $settings['soft_warn_pct'],
                $settings['max_prompt_chars'],
                $settings['max_output_tokens'],
                $settings['max_retries'],
                $settings['timeout_seconds'],
                $settings['intent_cache_ttl_hours'],
                $userId,
            ]
        );

        self::$cache = $settings;
        return $settings;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }
}
