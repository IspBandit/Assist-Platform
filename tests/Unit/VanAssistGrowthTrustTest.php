<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class VanAssistGrowthTrustTest extends TestCase
{
    public function testAdminRouteAndPermissionBoundSeoPublicationAreWired(): void
    {
        $routes = (string) file_get_contents(base_path('routes/admin.php'));
        $controller = (string) file_get_contents(base_path('app/Controllers/Admin/GrowthTrustController.php'));
        $service = (string) file_get_contents(base_path('app/Services/VanAssistGrowthService.php'));
        self::assertStringContainsString("/growth-trust", $routes);
        self::assertStringContainsString("requirePermission('seo.manage')", $controller);
        self::assertStringContainsString("noindex=0", $service);
        self::assertStringContainsString("evidence >= 3", $service);
        self::assertStringContainsString("AuditLog::record('seo.town_published'", $service);
    }

    public function testDashboardCoversEveryRequestedOperatingLoop(): void
    {
        $service = (string) file_get_contents(base_path('app/Services/VanAssistGrowthService.php'));
        foreach (['facility_summary', 'search_priorities', 'provider_trust', 'seo_candidates'] as $key) {
            self::assertStringContainsString("'{$key}'", $service);
        }
        $insights = (string) file_get_contents(base_path('app/Services/Demand/WebsiteInsightsService.php'));
        self::assertStringContainsString('impression_to_profile_rate', $insights);
        self::assertStringContainsString('profile_to_contact_rate', $insights);
    }
}
