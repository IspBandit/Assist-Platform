<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Request;
use App\Middleware\RequireBrandModule;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use PHPUnit\Framework\TestCase;

final class BrandModuleRoutingTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        parent::tearDown();
    }

    public function testVanAssistOnlyRouteFamiliesRequireConfiguredModules(): void
    {
        $web = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/web.php');
        $account = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/account.php');
        $provider = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/provider.php');
        $park = (string) file_get_contents(dirname(__DIR__, 2) . '/routes/park.php');

        self::assertStringContainsString("'module:parks'", $web);
        self::assertStringContainsString("'module:requests'", $web);
        self::assertStringContainsString("'module:service_runs'", $web);
        self::assertMatchesRegularExpression("/module:parks[\\s\\S]*caravan-parks|stays/", $web);
        self::assertMatchesRegularExpression("/module:requests[\\s\\S]*request-assistance/", $web);
        self::assertMatchesRegularExpression("/module:service_runs[\\s\\S]*service-runs/", $web);
        self::assertStringContainsString("'module:requests'", $account);
        self::assertStringContainsString("'module:requests'", $provider);
        self::assertStringContainsString("'module:service_runs'", $provider);
        self::assertStringContainsString("'module:parks'", $park);
    }

    public function testModuleMiddlewareIsRegisteredAndProviderNavigationIsScoped(): void
    {
        $kernel = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Core/Kernel.php');
        $nav = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/partials/provider-nav.php');
        $account = (string) file_get_contents(dirname(__DIR__, 2) . '/app/Views/account/dashboard.php');

        self::assertStringContainsString("aliasMiddleware('module'", $kernel);
        self::assertStringContainsString("moduleEnabled('requests')", $nav);
        self::assertStringContainsString("moduleEnabled('service_runs')", $nav);
        self::assertStringContainsString("moduleEnabled('requests')", $account);
        self::assertStringContainsString("moduleEnabled('service_runs')", $account);
    }

    public function testRequireBrandModuleDeniesDisabledRequestsAtRuntime(): void
    {
        $brand = Brand::fromArray('towsmart', [
            'database_id' => 2,
            'name' => 'TowSmart',
            'legal_name' => 'TowSmart',
            'short_name' => 'TowSmart',
            'status' => 'active',
            'url' => 'https://towsmart.test',
            'domains' => ['primary' => 'towsmart.test'],
            'assets' => [],
            'theme' => [],
            'metadata' => [],
            'contact' => [],
            'legal' => [],
            'navigation' => [],
            'footer' => [],
            'features' => [],
            'modules' => [
                'requests' => false,
                'service_runs' => false,
                'parks' => false,
            ],
            'analytics' => [],
            'search' => [],
            'storage_namespace' => 'towsmart',
        ]);
        BrandContext::set($brand);

        $middleware = new RequireBrandModule('requests');
        try {
            $middleware->handle(new Request('GET', '/request-assistance'), static fn () => 'ok');
            self::fail('Disabled module middleware must throw HttpException.');
        } catch (\App\Core\Exceptions\HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }
}
