<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Demand\VanAssistMonthlyPerformanceReport;
use PHPUnit\Framework\TestCase;

final class VanAssistMonthlyPerformanceReportTest extends TestCase
{
    public function testReportUsesPlainEnglishAndAvailableVanAssistMetrics(): void
    {
        $message = VanAssistMonthlyPerformanceReport::render('2026-08', [
            'summary' => [
                'visitors' => 240,
                'new_visitors' => 200,
                'returning_visitors' => 40,
                'multi_day_visitors' => 30,
                'page_views' => 610,
                'pages_per_visitor' => 2.5,
                'searches' => 120,
                'no_results' => 20,
                'exact_misses' => 40,
                'rescued_searches' => 20,
                'search_success_rate' => 83.3,
                'ask_searches' => 70,
                'ask_no_results' => 10,
                'stay_searches' => 50,
                'stay_no_results' => 12,
                'profile_views' => 90,
                'contact_actions' => 40,
                'confirmed_uses' => 8,
            ],
            'pages' => [
                ['label' => 'Places to stay', 'total' => 180, 'secondary' => 100],
            ],
            'sources' => [['label' => 'direct', 'total' => 150, 'secondary' => 380]],
            'devices' => [['label' => 'mobile', 'total' => 170, 'secondary' => 420]],
            'services' => [['label' => 'Dump points', 'total' => 50, 'secondary' => 8]],
            'locations' => [['label' => 'Gladstone', 'total' => 40, 'secondary' => 2]],
            'coverage_gaps' => [[
                'service_name' => 'Roof leaks', 'location_name' => 'Gladstone',
                'state_abbr' => 'QLD', 'searches' => 12, 'rescued_searches' => 5,
            ]],
            'actions' => [['label' => 'directions', 'total' => 30, 'secondary' => 25]],
            'providers' => [['label' => 'Example Caravan Repairs', 'contacts' => 12, 'profile_views' => 28]],
            'providers_used' => [['label' => 'Trusted Mobile Mechanic', 'confirmed_uses' => 8, 'distinct_customers' => 6]],
            'providers_contacted' => [['label' => 'Example Caravan Repairs', 'contacts' => 40, 'phone' => 22]],
            'stays_engaged' => [['label' => 'Batehaven Beachside', 'contacts' => 12, 'assistance_requests' => 3, 'page_views' => 22]],
            'daily' => [['label' => '2026-08-15', 'total' => 40, 'secondary' => 18]],
            'comparison_summary' => [
                'visitors' => 200, 'page_views' => 500, 'searches' => 100, 'contact_actions' => 20,
            ],
        ]);

        self::assertSame('VanAssist monthly website performance — August 2026', $message['subject']);
        self::assertStringContainsString('In plain English', $message['html']);
        self::assertStringContainsString('Providers people actually used', $message['html']);
        self::assertStringContainsString('Trusted Mobile Mechanic', $message['html']);
        self::assertStringContainsString('Providers people contacted', $message['html']);
        self::assertStringContainsString('Places to stay people engaged with', $message['html']);
        self::assertStringContainsString('Batehaven Beachside', $message['html']);
        self::assertStringContainsString('Services people searched for', $message['html']);
        self::assertStringContainsString('Dump points', $message['html']);
        self::assertStringContainsString('Providers attracting interest', $message['html']);
        self::assertStringContainsString('Example Caravan Repairs', $message['html']);
        self::assertStringContainsString('Search gaps needing coverage', $message['html']);
        self::assertStringContainsString('Roof leaks — Gladstone, QLD', $message['html']);
        self::assertStringContainsString('Daily website pulse', $message['html']);
        self::assertStringContainsString('Stay searches with no result', $message['html']);
        self::assertStringContainsString('Confirmed provider uses', $message['html']);
        self::assertStringContainsString('Compared with the prior month', $message['text']);
        self::assertStringContainsString('visits: +20.0%', $message['text']);
        self::assertStringContainsString('aggregate, first-party VanAssist figures', $message['text']);
        self::assertStringContainsString('monetisation shortlist', $message['text']);
        self::assertStringNotContainsString('session_id', $message['html']);
    }

    public function testZeroTrafficReportWarnsThatTrackingHealthMayNeedChecking(): void
    {
        $message = VanAssistMonthlyPerformanceReport::render('2026-08', [
            'summary' => ['visitors' => 0, 'page_views' => 0],
        ]);

        self::assertStringContainsString('No public visitor activity was recorded for the month', $message['text']);
        self::assertStringContainsString('check the analytics task and live tracking health', $message['text']);
    }

    public function testInvalidReportMonthFailsClosed(): void
    {
        $this->expectException(\RuntimeException::class);
        VanAssistMonthlyPerformanceReport::render('08/2026', []);
    }

    public function testTaskIsRegisteredAndScheduledWithEmailQueueSafetyNet(): void
    {
        $runner = (string) file_get_contents(base_path('app/Services/CronRunner.php'));
        $seed = (string) file_get_contents(base_path('database/seeds/data.php'));
        $migration = (string) file_get_contents(base_path('database/migrations/141_register_vanassist_monthly_performance_email.sql'));
        $cron = (string) file_get_contents(base_path('infrastructure/binarylane/ops/assist-platform.cron'));
        $service = (string) file_get_contents(base_path('app/Services/Demand/VanAssistMonthlyPerformanceReport.php'));

        foreach ([$runner, $seed, $migration, $cron] as $source) {
            self::assertStringContainsString('vanassist_monthly_performance_email', $source);
        }
        self::assertStringContainsString('20 6 1 * *', $cron);
        self::assertStringContainsString("private const RECIPIENT = 'support@vanassist.com.au'", $service);
        self::assertStringContainsString('already_queued', $service);
        self::assertStringContainsString('EmailQueue::queueRawId', $service);
        self::assertStringContainsString('Places to stay people engaged with', $service);
        self::assertStringNotContainsString('mail(', $service);

        self::assertStringContainsString('vanassist_monthly_performance', $runner);
        self::assertStringContainsString('VanAssistMonthlyPerformanceReport', $runner);
        self::assertMatchesRegularExpression(
            "/'process_email_queue'\\s*=>\\s*static function/",
            $runner
        );
    }
}
