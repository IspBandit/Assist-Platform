<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Exceptions\AdminApiException;
use App\Core\Exceptions\HttpException;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Platform\Support\RequestContext;
use PHPUnit\Framework\TestCase;

final class AdminApiRoutingTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        RequestContext::clear();
        Config::set('admin_api.enabled', true);
        Config::set('admin_api.restricted', true);
        Config::set('admin_api.mfa_required', false);
        Config::set('admin_api.max_batch_size', 100);
        Config::set('admin_api.recycle_retention_days', 90);
        Config::set('admin_api.access_token_ttl_seconds', 900);
        Config::set('app.release', 'test-release');

        $this->router = new Router();
        $this->router->aliasMiddleware('admin_api_enabled', \App\Middleware\RequireAdminApiEnabled::class);
        $this->router->aliasMiddleware('admin_api_request', \App\Middleware\AdminApiRequest::class);
        $this->router->aliasMiddleware('admin_api_bearer', \App\Middleware\RequireAdminApiBearerPlaceholder::class);
        $register = require base_path('routes/api_v1_admin.php');
        self::assertIsCallable($register);
        $register($this->router);
    }

    protected function tearDown(): void
    {
        RequestContext::clear();
        parent::tearDown();
    }

    public function testHealthReturnsOkEnvelopeWhenEnabled(): void
    {
        $response = $this->dispatch('GET', '/api/v1/admin/health');
        $payload = $this->json($response);

        self::assertSame(200, $response->status());
        self::assertSame('ok', $payload['data']['status']);
        self::assertSame('v1', $payload['data']['api_version']);
        self::assertArrayHasKey('X-Request-ID', $response->headers());
    }

    public function testVersionAndCapabilitiesAreDocumented(): void
    {
        $version = $this->json($this->dispatch('GET', '/api/v1/admin/version'));
        self::assertSame('v1', $version['data']['api_version']);
        self::assertSame('test-release', $version['data']['release']);

        $capabilities = $this->json($this->dispatch('GET', '/api/v1/admin/capabilities'));
        self::assertTrue($capabilities['data']['enabled']);
        self::assertSame('planned', $capabilities['data']['resources']['stays']);
        self::assertSame('planned', $capabilities['data']['resources']['traveller_facilities']);
        self::assertArrayNotHasKey('facilities', $capabilities['data']['resources']);
    }

    public function testDisabledApiReturnsJsonUnavailable(): void
    {
        Config::set('admin_api.enabled', false);
        try {
            $this->dispatch('GET', '/api/v1/admin/health');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            $response = Kernel::adminApiExceptionResponse($e);
            $payload = $this->json($response);
            self::assertSame(503, $response->status());
            self::assertSame('api_disabled', $payload['error']['code']);
            self::assertArrayHasKey('request_id', $payload['error']);
        }
    }

    public function testUnknownAdminApiRouteMapsToJsonNotFound(): void
    {
        try {
            $this->dispatch('GET', '/api/v1/admin/does-not-exist');
            self::fail('Expected HttpException');
        } catch (HttpException $e) {
            $response = Kernel::adminApiHttpExceptionResponse($e);
            $payload = $this->json($response);
            self::assertSame(404, $response->status());
            self::assertSame('not_found', $payload['error']['code']);
        }
    }

    public function testProtectedRouteRequiresBearerPlaceholder(): void
    {
        try {
            $this->dispatch('GET', '/api/v1/admin/auth/me');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertSame('unauthenticated', $e->errorCode());
        }

        try {
            $this->dispatch('GET', '/api/v1/admin/auth/me', [
                'HTTP_AUTHORIZATION' => 'Bearer not-yet-verified',
            ]);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertSame('unauthenticated', $e->errorCode());
        }
    }

    public function testMethodNotAllowedIsMappedToJson(): void
    {
        try {
            $this->dispatch('POST', '/api/v1/admin/health');
            self::fail('Expected HttpException');
        } catch (HttpException $e) {
            $response = Kernel::adminApiHttpExceptionResponse($e);
            $payload = $this->json($response);
            self::assertSame(405, $response->status());
            self::assertSame('method_not_allowed', $payload['error']['code']);
        }
    }

    public function testAdminApiPathHelper(): void
    {
        self::assertTrue(Kernel::isAdminApiPath('/api/v1/admin/health'));
        self::assertTrue(Kernel::isAdminApiPath('/api/v1/admin'));
        self::assertFalse(Kernel::isAdminApiPath('/admin'));
        self::assertFalse(Kernel::isAdminApiPath('/api/v1/public'));
    }

    /** @param array<string,string> $server */
    private function dispatch(string $method, string $path, array $server = []): Response
    {
        RequestContext::clear();
        $request = new Request([], [], array_merge([
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
        ], $server), []);
        RequestContext::begin($request);

        return $this->router->dispatch($request);
    }

    /** @return array<string,mixed> */
    private function json(Response $response): array
    {
        return json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR);
    }
}
