<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WebsiteInsightsWiringTest extends TestCase
{
    public function testAnalyticsRunsBeforeResponseIsSent(): void
    {
        $source = (string) file_get_contents(base_path('app/Core/Kernel.php'));
        $analyticsPosition = strpos($source, 'Analytics::record($request, $response)');
        $sendPosition = strpos($source, '$response->send()');

        self::assertNotFalse($analyticsPosition);
        self::assertNotFalse($sendPosition);
        self::assertLessThan(
            $sendPosition,
            $analyticsPosition,
            'Analytics must run before response headers are sent so first-time visitors receive a session cookie.'
        );
    }

    public function testMobileHomeHeroDoesNotReserveAnEmptyImageScreen(): void
    {
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

        self::assertStringContainsString(
            '.hero--visual{min-height:0;padding-top:clamp(1.25rem,5vw,2rem);padding-bottom:1rem}',
            $css
        );
        self::assertStringNotContainsString('padding-top:13rem', $css);
        self::assertStringContainsString('rgba(4,19,25,.84)', $css);
    }

    public function testForwardMigrationScopesEveryWebsiteInterestTableByBrand(): void
    {
        $sql = (string) file_get_contents(base_path('database/migrations/077_brand_scoped_website_analytics.sql'));

        foreach (['page_views', 'analytics_events', 'provider_searches', 'provider_contact_actions', 'demand_gap_feedback', 'service_outcomes'] as $table) {
            self::assertStringContainsString('ALTER TABLE ' . $table, $sql);
        }
        self::assertGreaterThanOrEqual(6, substr_count($sql, 'ADD COLUMN brand_id'));
    }

    public function testAnalyticsWritersUseTrustedCurrentBrandContext(): void
    {
        foreach ([
            'app/Services/Analytics.php',
            'app/Services/Demand/ActivityTracker.php',
            'app/Services/Demand/DemandRecorder.php',
            'app/Services/Demand/OutcomeService.php',
        ] as $file) {
            $source = (string) file_get_contents(base_path($file));
            self::assertStringContainsString('current_brand()->databaseId()', $source, $file);
        }
    }

    public function testSharedDirectoryJourneysRecordSearchAndProviderInterest(): void
    {
        foreach (['app/Controllers/Site/ProviderController.php', 'app/Controllers/Site/CategoryController.php'] as $file) {
            $source = (string) file_get_contents(base_path($file));
            self::assertStringContainsString('DemandRecorder::recordSearch', $source, $file);
            self::assertStringContainsString('DemandRecorder::recordImpressions', $source, $file);
        }
    }

    public function testAdminDashboardExplainsAggregatePrivacyBoundary(): void
    {
        $view = (string) file_get_contents(base_path('app/Views/admin/demand/index.php'));
        self::assertStringContainsString('Anonymous visitors remain anonymous', $view);
        self::assertStringContainsString('does not store visitor IP addresses', $view);
        self::assertStringContainsString('Services people wanted', $view);
        self::assertStringContainsString('Providers attracting interest', $view);
        self::assertStringContainsString('What visitors clicked', $view);
        self::assertStringContainsString('Coverage gaps needing attention', $view);
        self::assertStringContainsString('underneath is the website address used by the system', $view);

        $service = (string) file_get_contents(base_path('app/Services/Demand/WebsiteInsightsService.php'));
        self::assertStringContainsString("'/' => 'Home page'", $service);
        self::assertStringContainsString("'/find' => 'Provider search results'", $service);
        self::assertStringContainsString("'coverage_gaps'", $service);
    }

    public function testBrandDashboardLinksToWebsiteInsights(): void
    {
        $dashboard = (string) file_get_contents(base_path('app/Views/admin/dashboard.php'));
        $layout = (string) file_get_contents(base_path('app/Views/layouts/admin.php'));
        self::assertStringContainsString('Website activity', $dashboard);
        self::assertStringContainsString("['Website insights', '/admin/demand']", $layout);
        self::assertStringContainsString('data-auto-refresh-seconds="10"', $layout);
    }

    public function testMobileHeroClipsHorizontalOverflowAndConstrainsSearchPanel(): void
    {
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));
        self::assertStringContainsString('overflow-x:clip', $css);
        self::assertStringContainsString('.hero-search-panel .search-card{width:100%;min-width:0;max-width:100%}', $css);
    }

    public function testMobileAdminAndInsightReportsUseCompactDisclosurePatterns(): void
    {
        $layout = (string) file_get_contents(base_path('app/Views/layouts/admin.php'));
        $insights = (string) file_get_contents(base_path('app/Views/admin/demand/index.php'));
        $review = (string) file_get_contents(base_path('app/Views/admin/data-sources/queue.php'));
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));
        $script = (string) file_get_contents(base_path('public/assets/js/admin-platform.js'));

        self::assertStringContainsString('aria-controls="admin-nav"', $layout);
        self::assertStringContainsString('data-mobile-collapse', $insights);
        self::assertStringContainsString('max-height:min(62vh,34rem)', $css);
        self::assertStringContainsString("matchMedia('(max-width: 720px)')", $script);
        self::assertStringContainsString('review-queue-table', $review);
        self::assertStringContainsString('content:attr(data-label)', $css);
    }
}
