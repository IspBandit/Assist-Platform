<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Budget\AIBudgetService;
use App\Platform\AiSearch\Budget\AiSettings;
use App\Platform\AiSearch\Cache\IntentCache;
use PHPUnit\Framework\TestCase;

final class AIBudgetAndCacheTest extends TestCase
{
    public function testHardStopOnMonthlyRequestCap(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.01, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 100,
            'monthly_request_cap' => 50,
            'daily_budget_aud' => 50.0,
            'monthly_budget_aud' => 200.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 1,
            'requests_month' => 50,
            'cost_today' => 0.1,
            'cost_month' => 1.0,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_HARD_STOP, $result['state']);
    }

    public function testHardStopOnMonthlyBudget(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(1.0, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 100,
            'monthly_request_cap' => 1000,
            'daily_budget_aud' => 50.0,
            'monthly_budget_aud' => 10.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 1,
            'requests_month' => 10,
            'cost_today' => 0.5,
            'cost_month' => 9.5,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_HARD_STOP, $result['state']);
    }

    public function testProviderDisabledState(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.0, [
            'ai_enabled' => true,
            'openai_enabled' => false,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 10,
            'monthly_request_cap' => 100,
            'daily_budget_aud' => 10.0,
            'monthly_budget_aud' => 100.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 0,
            'requests_month' => 0,
            'cost_today' => 0.0,
            'cost_month' => 0.0,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_PROVIDER_DISABLED, $result['state']);
    }

    public function testDefaultsDisablePaidAi(): void
    {
        $defaults = AiSettings::defaults();
        self::assertFalse($defaults['ai_enabled']);
        self::assertFalse($defaults['openai_enabled']);
        self::assertSame([], $defaults['model_allowlist']);

        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.0, $defaults, [
            'requests_today' => 0,
            'requests_month' => 0,
            'cost_today' => 0.0,
            'cost_month' => 0.0,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_AI_DISABLED, $result['state']);
    }

    public function testHardStopOnDailyRequestCap(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.01, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 10,
            'monthly_request_cap' => 100,
            'daily_budget_aud' => 50.0,
            'monthly_budget_aud' => 200.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 10,
            'requests_month' => 10,
            'cost_today' => 1.0,
            'cost_month' => 1.0,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_HARD_STOP, $result['state']);
    }

    public function testHardStopOnDailyBudget(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.5, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 100,
            'monthly_request_cap' => 1000,
            'daily_budget_aud' => 1.0,
            'monthly_budget_aud' => 50.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 1,
            'requests_month' => 1,
            'cost_today' => 0.6,
            'cost_month' => 0.6,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_HARD_STOP, $result['state']);
    }

    public function testZeroCapsBlockPaidAi(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.0, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 0,
            'monthly_request_cap' => 0,
            'daily_budget_aud' => 0.0,
            'monthly_budget_aud' => 0.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 0,
            'requests_month' => 0,
            'cost_today' => 0.0,
            'cost_month' => 0.0,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_HARD_STOP, $result['state']);
    }

    public function testSoftWarnDoesNotBlock(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.0, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => ['test-model'],
            'daily_request_cap' => 10,
            'monthly_request_cap' => 100,
            'daily_budget_aud' => 10.0,
            'monthly_budget_aud' => 100.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 8,
            'requests_month' => 8,
            'cost_today' => 1.0,
            'cost_month' => 1.0,
        ]);
        self::assertTrue($result['allowed']);
        self::assertSame(AIBudgetService::STATE_SOFT_WARN, $result['state']);
    }

    public function testEmptyAllowlistBlocksPaidAi(): void
    {
        $service = new AIBudgetService();
        $result = $service->evaluatePaidAiAttempt(0.0, [
            'ai_enabled' => true,
            'openai_enabled' => true,
            'model_allowlist' => [],
            'daily_request_cap' => 10,
            'monthly_request_cap' => 100,
            'daily_budget_aud' => 10.0,
            'monthly_budget_aud' => 100.0,
            'soft_warn_pct' => 80,
        ], [
            'requests_today' => 0,
            'requests_month' => 0,
            'cost_today' => 0.0,
            'cost_month' => 0.0,
        ]);
        self::assertFalse($result['allowed']);
        self::assertSame(AIBudgetService::STATE_HARD_STOP, $result['state']);
    }

    public function testCacheKeyIsStableAndBrandScoped(): void
    {
        $cache = new IntentCache();
        $a = $cache->buildKey('vanassist', 'dump point near batehaven');
        $b = $cache->buildKey('vanassist', 'dump point near batehaven');
        $c = $cache->buildKey('towsmart', 'dump point near batehaven');
        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertSame(64, strlen($a));
    }

    public function testMigrationDefinesCacheAndBudgetTablesWithoutSecrets(): void
    {
        $sql = file_get_contents(dirname(__DIR__, 3) . '/database/migrations/102_assist_ai_cache_budget.sql');
        self::assertNotFalse($sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_settings', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_intent_cache', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_usage_events', $sql);
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS ai_usage_daily', $sql);
        self::assertStringContainsString('ALTER TABLE ai_settings', $sql);
        self::assertStringContainsString('ADD COLUMN model_allowlist_json', $sql);
        self::assertStringContainsString('ALTER TABLE ai_usage_events', $sql);
        self::assertStringContainsString('ALTER TABLE ai_usage_daily', $sql);
        self::assertStringContainsString('ai_enabled', $sql);
        self::assertStringNotContainsString('api_key', $sql);
        self::assertStringNotContainsString('OPENAI', $sql);
    }

    public function testAdminRouteRegistered(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 3) . '/routes/admin.php');
        self::assertNotFalse($routes);
        self::assertStringContainsString('/ai-search', $routes);
        self::assertStringContainsString('AiSearchAdminController@index', $routes);
    }
}
