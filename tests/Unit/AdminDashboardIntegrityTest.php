<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AdminDashboardIntegrityTest extends TestCase
{
    public function testDashboardFailuresAreVisibleAndLoggedInsteadOfReportedAsZero(): void
    {
        $controller = $this->source('app/Controllers/Admin/AdminController.php');
        $view = $this->source('app/Views/admin/dashboard.php');

        self::assertStringContainsString('private array $dashboardWarnings = []', $controller);
        self::assertStringContainsString('private function count(string $metric, string $sql, array $params = []): ?int', $controller);
        self::assertStringContainsString("Logger::error('Admin dashboard data unavailable.'", $controller);
        self::assertStringContainsString('return null;', $controller);
        self::assertStringContainsString('Some dashboard data is unavailable.', $view);
        self::assertStringContainsString('these values are not being shown as zero', $view);
    }

    public function testBrandWorkspaceSwitchPersistsAndIsConsumedByTheAdminKernel(): void
    {
        $controller = $this->source('app/Controllers/Admin/PlatformController.php');
        $kernel = $this->source('app/Core/Kernel.php');
        $layout = $this->source('app/Views/layouts/admin.php');

        self::assertStringContainsString("Session::set('_admin_brand_id', \$target->databaseId())", $controller);
        self::assertStringContainsString("Session::get('_admin_brand_id', 0)", $kernel);
        self::assertStringContainsString('AdminBrandAccess::canAccess($userId, $override)', $kernel);
        self::assertStringContainsString("action=\"<?= e(url('admin/switch-brand')) ?>\"", $layout);
        self::assertStringContainsString("return \$this->redirect(\$hostBrand->url() . '/admin')", $controller);
    }

    public function testWebsiteInsightsActivationIsAuditableAndPrivacyDisclosed(): void
    {
        $migration = $this->source('database/migrations/078_activate_first_party_website_insights.sql');

        self::assertStringContainsString("VALUES ('analytics_enabled', '1'", $migration);
        self::assertStringContainsString("'demand_analytics',", $migration);
        self::assertStringContainsString('privacy-conscious first-party analytics', $migration);
        self::assertStringContainsString("body NOT LIKE '%privacy-conscious first-party analytics%'", $migration);
    }

    public function testRetiredProviderGraphicCreationAdminIsRemoved(): void
    {
        self::assertFileDoesNotExist(base_path('app/Controllers/Admin/PromotionsController.php'));
        self::assertFileDoesNotExist(base_path('app/Views/admin/promotions/index.php'));
        self::assertFileDoesNotExist(base_path('app/Views/admin/promotions/show.php'));
        self::assertStringNotContainsString('/promotions', $this->source('routes/admin.php'));
    }

    private function source(string $path): string
    {
        return (string) file_get_contents(base_path($path));
    }
}
