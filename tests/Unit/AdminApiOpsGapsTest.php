<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Api\AdminApiAiUsageService;
use App\Services\Api\AdminApiFeatureFlagService;
use App\Services\Api\AdminApiImportService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class AdminApiOpsGapsTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        parent::tearDown();
    }

    public function testFeatureFlagListIsReadOnlyCatalogue(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(AdminApiFeatureFlagService::class))->getFileName()
        );
        self::assertStringContainsString('FeatureFlag::all()', $source);
        self::assertStringContainsString("'writable' => false", $source);
        self::assertStringContainsString("'brand_scope' => 'platform'", $source);
        self::assertStringContainsString("'key' =>", $source);
        self::assertStringContainsString("'enabled' =>", $source);
        self::assertStringNotContainsString('FeatureFlag::set', $source);
    }

    public function testImportListReturnsSparseCollectionWhenJobsUnavailable(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(AdminApiImportService::class))->getFileName()
        );
        self::assertStringContainsString("tableExists('api_import_jobs')", $source);
        self::assertStringContainsString("'sparse' => true", $source);
        self::assertStringContainsString('error === [] ? null : $error', $source);

        // Force tableExists/connect failure so list() takes the sparse path without a real DB.
        Config::set('database', [
            'host' => '',
            'port' => 0,
            'name' => '',
            'charset' => 'utf8mb4',
            'user' => '',
            'password' => '',
            'options' => [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        ]);
        $pdo = new ReflectionProperty(Database::class, 'pdo');
        $pdo->setValue(null, null);

        BrandContext::set($this->vanAssistBrand());

        $result = (new AdminApiImportService())->list(new Request(
            [],
            [],
            [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/api/v1/admin/imports',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            []
        ));

        self::assertSame([], $result['items']);
        self::assertTrue($result['meta']['sparse']);
        self::assertFalse($result['meta']['has_more']);
        self::assertNull($result['meta']['next_cursor']);
        self::assertSame(0, $result['meta']['count']);
        self::assertNull($result['links']['next']);
    }

    public function testAiUsageSummaryDocumentsBudgetSnapshot(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(AdminApiAiUsageService::class))->getFileName()
        );

        self::assertStringContainsString("'budget' => \$budget", $source);
        self::assertStringContainsString("'budget' => \$this->budgetSnapshot()", $source);
        self::assertStringContainsString('AIBudgetService', $source);
        self::assertStringContainsString('daily_budget_aud', $source);
        self::assertStringContainsString('cost_today_aud', $source);
    }

    private function vanAssistBrand(): Brand
    {
        return BrandRegistry::fromArray([
            'vanassist' => [
                'database_id' => 1,
                'name' => 'VanAssist',
                'legal_name' => 'VanAssist Australia',
                'short_name' => 'VanAssist',
                'status' => 'active',
                'url' => 'https://vanassist.test',
                'domains' => ['primary' => 'vanassist.test'],
                'assets' => [],
                'theme' => ['brand' => '#087f7d'],
                'metadata' => [],
                'contact' => [],
                'legal' => [],
                'navigation' => [],
                'footer' => [],
                'features' => [],
                'modules' => ['public_application' => true, 'parks' => true],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'vanassist',
            ],
        ])->get('vanassist');
    }
}
