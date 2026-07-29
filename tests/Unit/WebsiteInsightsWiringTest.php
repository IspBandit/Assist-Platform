<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class WebsiteInsightsWiringTest extends TestCase
{
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
    }

    public function testBrandDashboardLinksToWebsiteInsights(): void
    {
        $dashboard = (string) file_get_contents(base_path('app/Views/admin/dashboard.php'));
        $layout = (string) file_get_contents(base_path('app/Views/layouts/admin.php'));
        self::assertStringContainsString('Website activity', $dashboard);
        self::assertStringContainsString("['Website insights', '/admin/demand']", $layout);
    }
}
