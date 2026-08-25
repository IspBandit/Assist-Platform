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

    public function testBotsAreNotRecordedOrIncludedInWebsiteFigures(): void
    {
        $writer = (string) file_get_contents(base_path('app/Services/Analytics.php'));
        $report = (string) file_get_contents(base_path('app/Services/Demand/WebsiteInsightsService.php'));

        self::assertStringContainsString('TrafficQuality::excludesCurrentRequest()', $writer);
        self::assertStringContainsString('PublicPageViewPolicy::sqlPredicate', $report);
        self::assertStringContainsString("TrafficQuality::eligibleSessionSql('ts')", $report);
        self::assertStringContainsString('JOIN tracking_sessions ts', $report);
    }

    public function testHomepageContainsThePlainLanguageAskForm(): void
    {
        $partial = (string) file_get_contents(base_path('app/Views/partials/ask-vanassist.php'));

        self::assertStringContainsString('name="q"', $partial);
        self::assertStringContainsString("action=\"<?= e(url('ask')) ?>\"", $partial);
        self::assertStringContainsString('We can look across providers, stays and traveller facilities.', $partial);
        self::assertStringContainsString('Try: dump point, pet-friendly stay, mobile mechanic, drinking water', $partial);
        self::assertStringNotContainsString('ask-vanassist-teaser', $partial);
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

    public function testStayAndAskDemandAreIncludedInWebsiteReporting(): void
    {
        $park = (string) file_get_contents(base_path('app/Controllers/Site/ParkController.php'));
        $tracker = (string) file_get_contents(base_path('app/Services/Demand/ActivityTracker.php'));
        $report = (string) file_get_contents(base_path('app/Services/Demand/WebsiteInsightsService.php'));

        self::assertStringContainsString("'stay_search_completed'", $tracker);
        self::assertStringContainsString("'no_stay_found'", $tracker);
        self::assertStringContainsString('ActivityTracker::record', $park);
        self::assertStringContainsString('assist_searches', $report);
        self::assertStringContainsString("'ask_searches'", $report);
        self::assertStringContainsString("'stay_searches'", $report);
        self::assertStringContainsString("'returning_visitors'", $report);
        self::assertStringContainsString("'multi_day_visitors'", $report);
    }

    public function testAskSearchesRetainAnExplicitTrafficQualityMarker(): void
    {
        $migration = (string) file_get_contents(base_path('database/migrations/134_assist_search_traffic_quality.sql'));
        $logger = (string) file_get_contents(base_path('app/Platform/AiSearch/Logging/AssistSearchLogger.php'));

        self::assertStringContainsString('ADD COLUMN is_excluded', $migration);
        self::assertStringContainsString('TrafficQuality::excludesCurrentRequest()', $logger);
        self::assertStringContainsString('is_excluded, created_at', $logger);
    }

    public function testSearchAttributionSurvivesProfileAndContactLinks(): void
    {
        $card = (string) file_get_contents(base_path('app/Views/partials/provider-result-card.php'));
        $profile = (string) file_get_contents(base_path('app/Views/public/provider-profile.php'));
        $map = (string) file_get_contents(base_path('app/Views/public/search-results.php'));

        self::assertStringContainsString('$profileUrl =', $card);
        self::assertStringContainsString("url('go/phone/' . \$slug) . \$contactQuery", $profile);
        self::assertStringContainsString("url('go/directions/'", $map);
        self::assertStringContainsString("'?s='", $map);
    }

    public function testProviderValueReportingIsBrandScoped(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/Demand/ReportingService.php'));

        self::assertStringContainsString('$brandId = current_brand()->databaseId()', $service);
        self::assertGreaterThanOrEqual(8, substr_count($service, 'brand_id = ?'));
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
        self::assertStringContainsString("'rescued_searches'", $service);
        self::assertStringContainsString('ps.exact_match_count=0', $service);
    }

    public function testBrandDashboardLinksToWebsiteInsights(): void
    {
        $dashboard = (string) file_get_contents(base_path('app/Views/admin/dashboard.php'));
        $layout = (string) file_get_contents(base_path('app/Views/layouts/admin.php'));
        self::assertStringContainsString('Website activity', $dashboard);
        self::assertStringContainsString("['Website insights', '/admin/demand']", $layout);
        self::assertStringContainsString('data-auto-refresh-seconds="10"', $layout);
        self::assertStringContainsString('Needs attention', $dashboard);
        self::assertStringContainsString('Directory and workspace totals', $dashboard);
        self::assertStringContainsString('Recent administrative activity', $dashboard);
    }

    public function testFreeGrowthLinksAreRecordedAndShownInPlainEnglish(): void
    {
        $tracking = (string) file_get_contents(base_path('app/Services/Demand/TrackingSession.php'));
        $insights = (string) file_get_contents(base_path('app/Views/admin/demand/index.php'));
        $adminScript = (string) file_get_contents(base_path('public/assets/js/admin-platform.js'));

        self::assertStringContainsString("\$_GET['utm_source']", $tracking);
        self::assertStringContainsString("'utm:'", $tracking);
        self::assertStringContainsString('Direct or untagged visit', $insights);
        self::assertStringContainsString("\$heading === 'Visitor sources'", $insights);
        self::assertStringContainsString('[data-copy-target]', $adminScript);
        self::assertStringContainsString('[data-native-share]', $adminScript);
    }

    public function testMobileHeroClipsHorizontalOverflowAndConstrainsSearchPanel(): void
    {
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));
        self::assertStringContainsString('overflow-x:clip', $css);
        self::assertStringContainsString('.hero-search-panel .search-card{width:100%;min-width:0;max-width:100%}', $css);
    }

    public function testHomeSearchUsesLocationFirstButManualPlaceWins(): void
    {
        $home = (string) file_get_contents(base_path('app/Views/public/home.php'));
        $script = (string) file_get_contents(base_path('public/assets/js/app.js'));

        self::assertStringContainsString('data-auto-location', $home);
        self::assertStringContainsString("'autoSubmit' => 'false'", $home);
        self::assertStringContainsString("form.setAttribute('data-location-manual', '1')", $script);
        self::assertStringContainsString("form.getAttribute('data-location-manual') !== '1'", $script);
    }

    public function testTownSuggestionsAnchorToTheLocationInput(): void
    {
        $script = (string) file_get_contents(base_path('public/assets/js/app.js'));
        $css = (string) file_get_contents(base_path('public/assets/css/app.css'));

        self::assertStringContainsString('input.offsetTop', $script);
        self::assertStringContainsString('input.offsetWidth', $script);
        self::assertStringContainsString("strong.textContent = primary", $script);
        self::assertStringContainsString('.location-field{position:relative', $css);
        self::assertStringContainsString('z-index: 120', $css);
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
