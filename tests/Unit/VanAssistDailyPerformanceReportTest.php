<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Demand\VanAssistDailyPerformanceReport;
use PHPUnit\Framework\TestCase;

final class VanAssistDailyPerformanceReportTest extends TestCase
{
    public function testReportUsesPlainEnglishAndAvailableVanAssistMetrics(): void
    {
        $message = VanAssistDailyPerformanceReport::render('2026-08-15', [
            'summary' => [
                'visitors' => 24,
                'page_views' => 61,
                'pages_per_visitor' => 2.5,
                'searches' => 12,
                'no_results' => 2,
                'search_success_rate' => 83.3,
                'profile_views' => 9,
                'contact_actions' => 4,
                'confirmed_uses' => 1,
            ],
            'pages' => [['label' => 'Places to stay', 'total' => 18, 'secondary' => 10]],
            'sources' => [['label' => 'direct', 'total' => 15, 'secondary' => 38]],
            'devices' => [['label' => 'mobile', 'total' => 17, 'secondary' => 42]],
            'services' => [['label' => 'Dump points', 'total' => 5, 'secondary' => 1]],
            'locations' => [['label' => 'Gladstone', 'total' => 4, 'secondary' => 0]],
            'coverage_gaps' => [[
                'service_name' => 'Roof leaks', 'location_name' => 'Gladstone',
                'state_abbr' => 'QLD', 'searches' => 3,
            ]],
            'actions' => [['label' => 'directions', 'total' => 3, 'secondary' => 3]],
            'providers' => [['label' => 'Example Caravan Repairs', 'contacts' => 2, 'profile_views' => 4]],
        ]);

        self::assertSame('VanAssist daily website performance — 15 Aug 2026', $message['subject']);
        self::assertStringContainsString('In plain English', $message['html']);
        self::assertStringContainsString('Most popular pages', $message['html']);
        self::assertStringContainsString('Places to stay', $message['html']);
        self::assertStringContainsString('Dump points', $message['html']);
        self::assertStringContainsString('Search gaps needing coverage', $message['html']);
        self::assertStringContainsString('Roof leaks — Gladstone, QLD', $message['html']);
        self::assertStringContainsString('Example Caravan Repairs', $message['html']);
        self::assertStringContainsString('aggregate, first-party VanAssist figures', $message['text']);
        self::assertStringNotContainsString('session_id', $message['html']);
    }

    public function testZeroTrafficReportWarnsThatTrackingHealthMayNeedChecking(): void
    {
        $message = VanAssistDailyPerformanceReport::render('2026-08-15', [
            'summary' => ['visitors' => 0, 'page_views' => 0],
        ]);

        self::assertStringContainsString('No public visitor activity was recorded', $message['text']);
        self::assertStringContainsString('check the analytics task and live tracking health', $message['text']);
    }

    public function testInvalidReportDateFailsClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        VanAssistDailyPerformanceReport::render('15/08/2026', []);
    }

    public function testTaskIsRegisteredAndScheduledAfterDailyAggregation(): void
    {
        $runner = (string) file_get_contents(base_path('app/Services/CronRunner.php'));
        $seed = (string) file_get_contents(base_path('database/seeds/data.php'));
        $migration = (string) file_get_contents(base_path('database/migrations/132_register_vanassist_daily_performance_email.sql'));
        $cron = (string) file_get_contents(base_path('infrastructure/binarylane/ops/assist-platform.cron'));
        $service = (string) file_get_contents(base_path('app/Services/Demand/VanAssistDailyPerformanceReport.php'));

        foreach ([$runner, $seed, $migration, $cron] as $source) {
            self::assertStringContainsString('vanassist_daily_performance_email', $source);
        }
        self::assertStringContainsString('15 6 * * *', $cron);
        self::assertStringContainsString("private const RECIPIENT = 'support@vanassist.com.au'", $service);
        self::assertStringContainsString('already_queued', $service);
        self::assertStringContainsString('EmailQueue::queueRawId', $service);
        self::assertStringNotContainsString('mail(', $service);
    }
}
