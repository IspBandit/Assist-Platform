<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Exceptions\AdminApiException;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Platform\Support\RequestContext;
use PHPUnit\Framework\TestCase;

final class AdminApiAuditSearchMfaTest extends TestCase
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

    public function testCapabilitiesReportAuditAndSearchGapsAsRead(): void
    {
        $payload = $this->json($this->dispatch('GET', '/api/v1/admin/capabilities'));

        self::assertSame('read', $payload['data']['resources']['audit']);
        self::assertSame('read', $payload['data']['resources']['search_gaps']);
        self::assertSame('active', $payload['data']['authentication']['mfa_verify']);
        self::assertSame('active', $payload['data']['authentication']['mfa_enroll']);
    }

    public function testAuditAndSearchGapsRequireBearer(): void
    {
        foreach (['/api/v1/admin/audit', '/api/v1/admin/search-gaps'] as $path) {
            try {
                $this->dispatch('GET', $path);
                self::fail('Expected AdminApiException for ' . $path);
            } catch (AdminApiException $e) {
                self::assertSame(401, $e->getStatusCode(), $path);
                self::assertSame('unauthenticated', $e->errorCode());
            }
        }
    }

    public function testMfaEndpointsRequireHumanBearer(): void
    {
        foreach (
            [
                ['POST', '/api/v1/admin/auth/mfa/challenge', []],
                ['POST', '/api/v1/admin/auth/mfa/enroll/begin', []],
                ['POST', '/api/v1/admin/auth/mfa/enroll/confirm', ['code' => '123456']],
                ['POST', '/api/v1/admin/auth/mfa/verify', ['code' => '123456']],
            ] as [$method, $path, $body]
        ) {
            try {
                $this->dispatch($method, $path, [], $body);
                self::fail('Expected AdminApiException for ' . $method . ' ' . $path);
            } catch (AdminApiException $e) {
                self::assertSame(401, $e->getStatusCode(), $method . ' ' . $path);
                self::assertSame('unauthenticated', $e->errorCode());
            }
        }
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
