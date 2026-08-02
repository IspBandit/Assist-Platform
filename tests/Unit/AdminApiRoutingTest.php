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
        Config::set('admin_api.refresh_token_ttl_seconds', 604800);
        Config::set('admin_api.service_token_ttl_seconds', 3600);
        Config::set('app.release', 'test-release');

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
        self::assertSame('active', $capabilities['data']['authentication']['human_password']);
        self::assertSame('active', $capabilities['data']['authentication']['refresh_tokens']);
        self::assertSame('active', $capabilities['data']['authentication']['service_accounts']);
        self::assertArrayHasKey('scopes', $capabilities['data']);
        self::assertTrue($capabilities['data']['scopes']['providers:read']['service']);
        self::assertSame('read_write', $capabilities['data']['resources']['providers']);
        self::assertSame('read_write', $capabilities['data']['resources']['stays']);
        self::assertSame('read_write', $capabilities['data']['resources']['recycle_bin']);
        self::assertSame('read_write', $capabilities['data']['resources']['drafts']);
        self::assertSame('read_write', $capabilities['data']['resources']['imports']);
        self::assertSame('read', $capabilities['data']['resources']['audit']);
        self::assertSame('read', $capabilities['data']['resources']['search_gaps']);
        self::assertSame('read_write', $capabilities['data']['resources']['facilities']);
        self::assertSame('read_write', $capabilities['data']['resources']['claims']);
        self::assertSame('read', $capabilities['data']['resources']['ai_usage']);
        self::assertArrayNotHasKey('traveller_facilities', $capabilities['data']['resources']);
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

    public function testProtectedRouteRequiresBearer(): void
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
                'HTTP_AUTHORIZATION' => 'Token not-a-bearer',
            ]);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertSame('unauthenticated', $e->errorCode());
        }
    }

    public function testLoginValidationFailsClosedWithoutDatabase(): void
    {
        try {
            $this->dispatch('POST', '/api/v1/admin/auth/login', [], []);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('validation_failed', $e->errorCode());
            self::assertArrayHasKey('email', $e->fields());
            self::assertArrayHasKey('password', $e->fields());
        }
    }

    public function testRefreshValidationRequiresToken(): void
    {
        try {
            $this->dispatch('POST', '/api/v1/admin/auth/refresh', [], ['refresh_token' => '']);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('validation_failed', $e->errorCode());
        }
    }

    public function testServiceAccountsRequireBearer(): void
    {
        try {
            $this->dispatch('GET', '/api/v1/admin/service-accounts');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
            self::assertSame('unauthenticated', $e->errorCode());
        }
    }

    public function testProvidersAndStaysRequireBearer(): void
    {
        foreach (['/api/v1/admin/providers', '/api/v1/admin/stays'] as $path) {            try {
                $this->dispatch('GET', $path);
                self::fail('Expected AdminApiException for ' . $path);
            } catch (AdminApiException $e) {
                self::assertSame(401, $e->getStatusCode());
                self::assertSame('unauthenticated', $e->errorCode());
            }
        }
    }

    public function testWriteRoutesRequireBearer(): void
    {
        $routes = [
            ['POST', '/api/v1/admin/providers', ['business_name' => 'Test Co']],
            ['PATCH', '/api/v1/admin/providers/1', ['business_name' => 'Test Co']],
            ['POST', '/api/v1/admin/providers/1/publish', []],
            ['POST', '/api/v1/admin/providers/1/unpublish', []],
            ['POST', '/api/v1/admin/providers/1/archive', []],
            ['POST', '/api/v1/admin/providers/1/restore', []],
            ['DELETE', '/api/v1/admin/providers/1', ['reason' => 'duplicate listing']],
            ['POST', '/api/v1/admin/stays', ['name' => 'Test Stay']],
            ['PATCH', '/api/v1/admin/stays/1', ['name' => 'Test Stay']],
            ['POST', '/api/v1/admin/stays/1/publish', []],
            ['DELETE', '/api/v1/admin/stays/1', ['reason' => 'closed permanently']],
            ['GET', '/api/v1/admin/recycle-bin', []],
            ['POST', '/api/v1/admin/recycle-bin/provider/1/restore', []],
            ['DELETE', '/api/v1/admin/recycle-bin/provider/1/purge', ['confirm' => true, 'reason' => 'duplicate']],
            ['POST', '/api/v1/admin/recycle-bin/bulk-restore', ['confirm' => true, 'items' => []]],
            ['GET', '/api/v1/admin/drafts', []],
            ['POST', '/api/v1/admin/drafts', ['entity_type' => 'provider', 'payload' => ['business_name' => 'Test']]],
            ['POST', '/api/v1/admin/drafts/abc/approve', []],
            ['POST', '/api/v1/admin/imports', ['checksum' => str_repeat('a', 64), 'items' => []]],
            ['GET', '/api/v1/admin/audit', []],
            ['GET', '/api/v1/admin/search-gaps', []],
            ['POST', '/api/v1/admin/auth/mfa/challenge', []],
            ['POST', '/api/v1/admin/auth/mfa/verify', ['code' => '123456']],
        ];

        foreach ($routes as [$method, $path, $body]) {
            try {
                $this->dispatch($method, $path, [], $body);
                self::fail('Expected AdminApiException for ' . $method . ' ' . $path);
            } catch (AdminApiException $e) {
                self::assertSame(401, $e->getStatusCode(), $method . ' ' . $path);
                self::assertSame('unauthenticated', $e->errorCode());
            }
        }
    }

    public function testTokenValidationFailsClosedWithoutDatabase(): void
    {
        try {
            $this->dispatch('POST', '/api/v1/admin/auth/token', [], [
                'client_key' => '',
                'client_secret' => '',
            ]);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertSame('validation_failed', $e->errorCode());
            self::assertArrayHasKey('client_key', $e->fields());
            self::assertArrayHasKey('client_secret', $e->fields());
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

    /**
     * @param array<string,string> $server
     * @param array<string,mixed> $body
     */
    private function dispatch(string $method, string $path, array $server = [], array $body = []): Response
    {
        RequestContext::clear();
        $request = new Request([], $body, array_merge([
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
