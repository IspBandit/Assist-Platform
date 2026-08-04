<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Platform\Support\RequestContext;
use App\Services\Api\AdminApiCorrectionService;
use App\Services\Api\AdminApiDuplicateService;
use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;

final class AdminApiOptionBResourcesTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        RequestContext::clear();
        Config::set('admin_api.enabled', true);
        Config::set('admin_api.restricted', true);
        Config::set('admin_api.mfa_required', false);

        $this->router = new Router();
        $this->router->aliasMiddleware('admin_api_enabled', \App\Middleware\RequireAdminApiEnabled::class);
        $this->router->aliasMiddleware('admin_api_request', \App\Middleware\AdminApiRequest::class);
        $this->router->aliasMiddleware('admin_api_bearer', \App\Middleware\RequireAdminApiBearer::class);
        $this->router->aliasMiddleware('admin_api_human', \App\Middleware\RequireAdminApiHuman::class);
        $this->router->aliasMiddleware('admin_api_scope', \App\Middleware\RequireAdminApiScope::class);
        $register = require base_path('routes/api_v1_admin.php');
        self::assertIsCallable($register);
        $register($this->router);
    }

    protected function tearDown(): void
    {
        RequestContext::clear();
        parent::tearDown();
    }

    public function testOptionBScopesAreRegistered(): void
    {
        foreach ([
            'claims:read',
            'claims:write',
            'corrections:read',
            'corrections:write',
            'duplicates:read',
            'datasets:read',
            'datasets:write',
            'facilities:read',
            'facilities:write',
            'ai:read',
        ] as $scope) {
            self::assertContains($scope, AdminApiScopes::ALL, 'Missing scope ' . $scope);
        }

        self::assertContains('duplicates:merge', AdminApiScopes::NEVER_SERVICE);
        self::assertContains('drafts:approve', AdminApiScopes::NEVER_SERVICE);
        self::assertContains('claims:read', AdminApiScopes::RIC_SERVICE);
        self::assertContains('datasets:read', AdminApiScopes::RIC_SERVICE);
        self::assertContains('facilities:read', AdminApiScopes::RIC_SERVICE);
        self::assertContains('ai:read', AdminApiScopes::RIC_SERVICE);
        self::assertContains('corrections:read', AdminApiScopes::RIC_SERVICE);
        self::assertContains('duplicates:read', AdminApiScopes::RIC_SERVICE);
        self::assertContains('recycle_bin:restore', AdminApiScopes::RIC_SERVICE);
    }

    public function testOptionBRouteSignaturesExist(): void
    {
        $registered = $this->registeredRouteSignatures();
        foreach ([
            'GET /claims',
            'GET /claims/{id}',
            'POST /claims/{id}/approve',
            'GET /corrections',
            'POST /corrections/{id}/approve',
            'GET /duplicates',
            'POST /duplicates/check',
            'POST /duplicates/{id}/merge',
            'GET /datasets',
            'PATCH /datasets/{id}',
            'GET /ai/usage/summary',
            'GET /searches',
            'GET /sync-conflicts',
            'GET /facilities',
            'GET /imports',
            'POST /imports/{id}/publish',
            'GET /feature-flags',
            'GET /overview',
            'GET /website-insights',
        ] as $required) {
            self::assertContains($required, $registered, 'Missing route ' . $required);
        }
    }

    public function testCapabilitiesExposeOptionBResources(): void
    {
        $payload = $this->json($this->dispatch('GET', '/api/v1/admin/capabilities'));

        self::assertSame('read_write', $payload['data']['resources']['facilities']);
        self::assertSame('read_write', $payload['data']['resources']['claims']);
        self::assertSame('read_write', $payload['data']['resources']['duplicates']);
        self::assertSame('read', $payload['data']['resources']['ai_usage']);
        self::assertTrue($payload['data']['scopes']['claims:read']['service']);
        self::assertFalse($payload['data']['scopes']['duplicates:merge']['service']);
    }

    public function testCorrectionRejectRequiresReason(): void
    {
        try {
            (new AdminApiCorrectionService())->reject(1, ['reason' => 'no'], $this->request());
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('reason', $e->fields());
        }
    }

    public function testDuplicateMergeSupportsDryRunInService(): void
    {
        $source = (string) file_get_contents(base_path('app/Services/Api/AdminApiDuplicateService.php'));
        self::assertStringContainsString('dry_run', $source);
        self::assertStringContainsString('soft_delete_absorbed_provider', $source);
    }

    /** @return list<string> */
    private function registeredRouteSignatures(): array
    {
        $source = (string) file_get_contents(base_path('routes/api_v1_admin.php'));
        preg_match_all(
            "/\\\$router->(get|post|put|patch|delete)\\('([^']+)'/",
            $source,
            $matches,
            PREG_SET_ORDER
        );

        $signatures = [];
        foreach ($matches as $match) {
            $signatures[] = strtoupper($match[1]) . ' ' . $match[2];
        }

        return $signatures;
    }

    private function request(): Request
    {
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/corrections/1/reject',
            'HTTP_HOST' => 'localhost',
        ], []);
        RequestContext::begin($request);

        return $request;
    }

    /** @return array<string,mixed> */
    private function json(Response $response): array
    {
        return json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
    }

    private function dispatch(string $method, string $path): Response
    {
        RequestContext::clear();
        $request = new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);
        RequestContext::begin($request);

        return $this->router->dispatch($request);
    }
}
