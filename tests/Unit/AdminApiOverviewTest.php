<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Platform\Support\RequestContext;
use App\Services\Api\AdminApiContext;
use App\Services\Api\AdminApiOverviewService;
use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;

final class AdminApiOverviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RequestContext::clear();
        AdminApiContext::clear();
        BrandContext::clear();
        Config::set('admin_api.enabled', true);
        Config::set('admin_api.restricted', true);
        Config::set('app.release', 'overview-test');
    }

    protected function tearDown(): void
    {
        AdminApiContext::clear();
        BrandContext::clear();
        RequestContext::clear();
        parent::tearDown();
    }

    public function testOverviewAndWebsiteInsightRoutesRegistered(): void
    {
        $source = (string) file_get_contents(base_path('routes/api_v1_admin.php'));
        self::assertStringContainsString("'/overview'", $source);
        self::assertStringContainsString("'/website-insights'", $source);
        self::assertStringContainsString('OverviewController@overview', $source);
        self::assertStringContainsString('OverviewController@websiteInsights', $source);
    }

    public function testRicServiceIncludesOverviewReadScopes(): void
    {
        $scopes = AdminApiScopes::RIC_SERVICE;
        self::assertContains('analytics:read', $scopes);
        self::assertContains('ai:read', $scopes);
        self::assertContains('corrections:read', $scopes);
        self::assertContains('duplicates:read', $scopes);
        AdminApiScopes::rejectForbiddenForService($scopes);
    }

    public function testOverviewReturnsStableShapeWithoutDatabase(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setService(
            ['id' => 'svc-test', 'name' => 'overview-test'],
            AdminApiScopes::RIC_SERVICE,
            'token-test'
        );

        $payload = (new AdminApiOverviewService())->overview($this->request('GET', '/api/v1/admin/overview?range=7d'));

        self::assertSame('v1', $payload['system']['api_version']);
        self::assertSame('ok', $payload['system']['api_status']);
        self::assertSame('overview-test', $payload['system']['release']);
        self::assertSame('vanassist', $payload['brand']['key']);
        self::assertArrayHasKey('from', $payload['range']);
        self::assertArrayHasKey('to', $payload['range']);
        self::assertArrayHasKey('website', $payload);
        self::assertArrayHasKey('queues', $payload);
        self::assertTrue($payload['queues']['claims_pending']['available']);
        self::assertTrue($payload['queues']['corrections_pending']['available']);
        self::assertTrue($payload['ai']['available']);
        self::assertTrue($payload['datasets']['available']);
        self::assertIsArray($payload['attention']);
        self::assertIsArray($payload['warnings']);
        self::assertArrayHasKey('website', $payload['labels']);
    }

    public function testOverviewHidesScopedSectionsWithoutPermission(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setService(
            ['id' => 'svc-limited', 'name' => 'limited'],
            ['analytics:read'],
            'token-limited'
        );

        $payload = (new AdminApiOverviewService())->overview($this->request());

        self::assertFalse($payload['queues']['claims_pending']['available']);
        self::assertNull($payload['queues']['claims_pending']['count']);
        self::assertFalse($payload['ai']['available']);
        self::assertFalse($payload['datasets']['available']);
    }

    public function testWebsiteInsightsReturnsStableShapeWithoutDatabase(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setService(
            ['id' => 'svc-test', 'name' => 'overview-test'],
            ['analytics:read'],
            'token-test'
        );

        $payload = (new AdminApiOverviewService())->websiteInsights(
            $this->request('GET', '/api/v1/admin/website-insights?range=30d')
        );

        self::assertSame('vanassist', $payload['brand_key']);
        self::assertArrayHasKey('summary', $payload);
        self::assertArrayHasKey('filtered_bot_page_views', $payload);
        self::assertArrayHasKey('labels', $payload);
        self::assertArrayHasKey('available', $payload);
    }

    private function request(string $method = 'GET', string $uri = '/api/v1/admin/overview'): Request
    {
        $request = new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);
        RequestContext::begin($request);

        return $request;
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
