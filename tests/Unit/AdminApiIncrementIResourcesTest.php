<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Api\AdminApiCategoryService;
use App\Services\Api\AdminApiLocationService;
use App\Services\Api\AdminApiOpsService;
use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

final class AdminApiIncrementIResourcesTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        parent::tearDown();
    }

    public function testIncrementIScopesAreRegisteredForRic(): void
    {
        foreach (['ops:read', 'categories:read', 'locations:read'] as $scope) {
            self::assertContains($scope, AdminApiScopes::ALL);
            self::assertContains($scope, AdminApiScopes::RIC_SERVICE);
            self::assertNotContains($scope, AdminApiScopes::NEVER_SERVICE);
            $catalog = AdminApiScopes::catalog();
            self::assertArrayHasKey($scope, $catalog);
            self::assertTrue($catalog[$scope]['service']);
        }
    }

    public function testSparseReadsWhenTablesUnavailable(): void
    {
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
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/v1/admin/ops/failed-emails',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        $ops = new AdminApiOpsService();
        $emails = $ops->listFailedEmails($request);
        self::assertSame([], $emails['items']);
        self::assertTrue($emails['meta']['sparse']);
        self::assertSame('email_queue_missing', $emails['meta']['source']);

        $tasks = $ops->listFailedScheduledTasks($request);
        self::assertSame([], $tasks['items']);
        self::assertTrue($tasks['meta']['sparse']);

        $categories = (new AdminApiCategoryService())->list($request);
        self::assertSame([], $categories['items']);
        self::assertTrue($categories['meta']['sparse']);

        $towns = (new AdminApiLocationService())->listTowns($request);
        self::assertSame([], $towns['items']);
        self::assertTrue($towns['meta']['sparse']);
    }

    public function testOpsServiceOmitsEmailBodies(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(AdminApiOpsService::class))->getFileName());
        self::assertStringNotContainsString('html_body', $source);
        self::assertStringNotContainsString('text_body', $source);
        self::assertStringContainsString('failed_emails', $source);
        self::assertStringContainsString('failed_scheduled_tasks', $source);
    }

    public function testCategoryServiceTargetsBrandProviderCategories(): void
    {
        $source = (string) file_get_contents((new ReflectionClass(AdminApiCategoryService::class))->getFileName());
        self::assertStringContainsString('brand_provider_categories', $source);
        self::assertStringNotContainsString('FROM service_categories', $source);
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
