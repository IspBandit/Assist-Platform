<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Budget\AiCostSimulator;
use App\Platform\AiSearch\Privacy\LocationPrivacy;
use App\Platform\AiSearch\Retention\AiRetentionService;
use App\Platform\AiSearch\Support\AiReleaseGate;
use PHPUnit\Framework\TestCase;

final class AiHardeningTest extends TestCase
{
    public function testLocationPrivacyRoundsAndRejectsInvalid(): void
    {
        self::assertSame([-35.71, 150.18], LocationPrivacy::roundCoords(-35.70891, 150.17822, 2));
        self::assertNull(LocationPrivacy::roundCoords(null, 150.0));
        self::assertNull(LocationPrivacy::roundCoords(91.0, 150.0));
        self::assertSame(
            ['town_id', 'radius_km', 'location_precision'],
            LocationPrivacy::allowedAssistSearchLocationFields()
        );
    }

    public function testCostSimulatorScalesWithHitRate(): void
    {
        $low = AiCostSimulator::simulate('gpt-4o-mini', 100, 10.0, 800, 500);
        $high = AiCostSimulator::simulate('gpt-4o-mini', 100, 100.0, 800, 500);
        self::assertSame(10, $low['daily_ai_calls']);
        self::assertSame(100, $high['daily_ai_calls']);
        self::assertGreaterThan($low['monthly_aud'], $high['monthly_aud']);
        self::assertSame(0.0, AiCostSimulator::simulate('gpt-4o-mini', 100, 0.0)['daily_aud']);
    }

    public function testRetentionWindowsHaveMinimums(): void
    {
        $windows = AiRetentionService::windows();
        self::assertGreaterThanOrEqual(30, $windows['assist_searches_days']);
        self::assertGreaterThanOrEqual(30, $windows['usage_events_days']);
        self::assertGreaterThanOrEqual(30, $windows['gap_events_days']);
    }

    public function testAskRouteUsesTightenedRateLimitAndCronRegistersRetention(): void
    {
        $routes = (string) file_get_contents(dirname(__DIR__, 3) . '/routes/web.php');
        self::assertStringContainsString('ask_rate:public.ask-vanassist,20,3600,3600', $routes);
        self::assertStringContainsString('ask.unlock', $routes);
        self::assertStringContainsString('ask.click', $routes);

        $cron = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Services/CronRunner.php');
        self::assertStringContainsString("'ai_retention'", $cron);

        $migration = (string) file_get_contents(dirname(__DIR__, 3) . '/database/migrations/107_assist_ai_hardening.sql');
        self::assertStringContainsString('ai_retention', $migration);
    }

    public function testReleaseGateReportsAskRateLimitWiring(): void
    {
        $gate = (new AiReleaseGate())->evaluate();
        self::assertArrayHasKey('status', $gate);
        self::assertNotEmpty($gate['checks']);
        $byId = [];
        foreach ($gate['checks'] as $check) {
            $byId[$check['id']] = $check;
        }
        self::assertTrue($byId['ask_rate_limit_wired']['ok']);
        self::assertTrue($byId['ask_captcha_escalation_wired']['ok']);
        self::assertTrue($byId['osm_offline_seed_wired']['ok']);
        self::assertTrue($byId['data012_ingest_wired']['ok']);
        self::assertTrue($byId['traveller_facilities_router']['ok']);
        self::assertArrayHasKey('traveller_facilities_populated', $byId);
    }
}
