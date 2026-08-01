<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Router;
use App\Platform\Support\RequestContext;
use App\Services\Api\AdminApiAuthService;
use App\Services\Api\AdminApiContext;
use PHPUnit\Framework\TestCase;

/**
 * Human Admin API auth against a disposable migrated database.
 * Enable with RUN_INTEGRATION_TESTS=1.
 */
final class AdminApiAuthIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_INTEGRATION_TESTS=1 with a disposable database');
        }

        foreach (['api_access_tokens', 'api_refresh_tokens', 'api_login_throttle', 'api_security_events'] as $table) {
            if (!Database::tableExists($table)) {
                $this->markTestSkipped("Migration required: missing {$table}");
            }
        }

        RequestContext::clear();
        AdminApiContext::clear();
        Config::set('admin_api.enabled', true);
        Config::set('admin_api.restricted', false);
        Config::set('admin_api.mfa_required', false);
        Config::set('admin_api.access_token_ttl_seconds', 900);
        Config::set('admin_api.refresh_token_ttl_seconds', 604800);
    }

    protected function tearDown(): void
    {
        AdminApiContext::clear();
        RequestContext::clear();
        parent::tearDown();
    }

    public function testLoginRefreshMeAndLogoutRoundTrip(): void
    {
        $email = getenv('ADMIN_API_TEST_EMAIL');
        $password = getenv('ADMIN_API_TEST_PASSWORD');
        if (!is_string($email) || $email === '' || !is_string($password) || $password === '') {
            $this->markTestSkipped('Set ADMIN_API_TEST_EMAIL and ADMIN_API_TEST_PASSWORD for auth round-trip');
        }

        $auth = new AdminApiAuthService();
        $request = $this->request('POST', '/api/v1/admin/auth/login');
        $bundle = $auth->login($email, $password, $request, 'phpunit');

        self::assertArrayHasKey('access_token', $bundle);
        self::assertArrayHasKey('refresh_token', $bundle);
        self::assertSame('Bearer', $bundle['token_type']);

        $user = $auth->authenticateAccessToken($bundle['access_token'], $request);
        self::assertNotNull($user);
        self::assertSame((int) $bundle['user']['id'], (int) $user['id']);

        $refreshed = $auth->refresh($bundle['refresh_token'], $request);
        self::assertNotSame($bundle['access_token'], $refreshed['access_token']);
        self::assertNotSame($bundle['refresh_token'], $refreshed['refresh_token']);

        try {
            $auth->refresh($bundle['refresh_token'], $request);
            self::fail('Expected reuse of rotated refresh token to fail');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
        }

        AdminApiContext::clear();
        $authed = $auth->authenticateAccessToken($refreshed['access_token'], $request);
        self::assertNotNull($authed);
        $auth->logout($refreshed['refresh_token'], AdminApiContext::accessTokenId(), $request, false);

        AdminApiContext::clear();
        self::assertNull($auth->authenticateAccessToken($refreshed['access_token'], $request));
    }

    public function testProtectedMeRouteRejectsMissingBearerViaRouter(): void
    {
        $router = new Router();
        $router->aliasMiddleware('admin_api_enabled', \App\Middleware\RequireAdminApiEnabled::class);
        $router->aliasMiddleware('admin_api_request', \App\Middleware\AdminApiRequest::class);
        $router->aliasMiddleware('admin_api_bearer', \App\Middleware\RequireAdminApiBearer::class);
        $register = require base_path('routes/api_v1_admin.php');
        self::assertIsCallable($register);
        $register($router);

        try {
            $router->dispatch($this->request('GET', '/api/v1/admin/auth/me'));
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
        }
    }

    private function request(string $method, string $path): Request
    {
        $request = new Request([], [], [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $path,
            'HTTP_HOST' => 'localhost',
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'phpunit-admin-api',
        ], []);
        RequestContext::begin($request);

        return $request;
    }
}
