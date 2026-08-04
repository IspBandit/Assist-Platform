<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Core\Config;
use App\Core\Exceptions\AdminApiException;
use App\Core\Kernel;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Platform\Support\RequestContext;
use App\Services\Api\AdminApiEnvelope;
use PHPUnit\Framework\TestCase;

/**
 * RIC mock-client contract checks for Phase 1 Admin API (CORE-011 Increment 9).
 */
final class AdminApiRicContractTest extends TestCase
{
    /** @var list<string> */
    private const PHASE1_REQUIRED_PATHS = [
        'GET /health',
        'GET /version',
        'GET /capabilities',
        'POST /auth/login',
        'POST /auth/refresh',
        'POST /auth/logout',
        'POST /auth/token',
        'GET /auth/me',
        'GET /auth/sessions',
        'DELETE /auth/sessions/{id}',
        'POST /auth/mfa/challenge',
        'POST /auth/mfa/enroll/begin',
        'POST /auth/mfa/enroll/confirm',
        'POST /auth/mfa/verify',
        'GET /service-accounts',
        'POST /service-accounts',
        'GET /service-accounts/{id}',
        'PATCH /service-accounts/{id}',
        'POST /service-accounts/{id}/rotate',
        'DELETE /service-accounts/{id}',
        'GET /providers',
        'GET /providers/{id}',
        'POST /providers',
        'PATCH /providers/{id}',
        'POST /providers/{id}/publish',
        'POST /providers/{id}/unpublish',
        'POST /providers/{id}/archive',
        'POST /providers/{id}/restore',
        'DELETE /providers/{id}',
        'GET /stays',
        'GET /stays/{id}',
        'POST /stays',
        'PATCH /stays/{id}',
        'POST /stays/{id}/publish',
        'POST /stays/{id}/unpublish',
        'POST /stays/{id}/archive',
        'POST /stays/{id}/restore',
        'DELETE /stays/{id}',
        'GET /recycle-bin',
        'GET /recycle-bin/{entity_type}/{id}',
        'POST /recycle-bin/{entity_type}/{id}/restore',
        'DELETE /recycle-bin/{entity_type}/{id}/purge',
        'POST /recycle-bin/bulk-restore',
        'POST /recycle-bin/bulk-purge',
        'GET /drafts',
        'GET /drafts/{id}',
        'POST /drafts',
        'PATCH /drafts/{id}',
        'POST /drafts/{id}/approve',
        'POST /drafts/{id}/reject',
        'POST /imports',
        'GET /imports',
        'GET /imports/{id}',
        'POST /imports/{id}/validate',
        'POST /imports/{id}/stage',
        'POST /imports/{id}/publish',
        'POST /imports/{id}/cancel',
        'POST /imports/{id}/retry',
        'GET /claims',
        'GET /claims/{id}',
        'POST /claims/{id}/approve',
        'POST /claims/{id}/reject',
        'POST /claims/{id}/request-evidence',
        'GET /corrections',
        'GET /corrections/{id}',
        'POST /corrections/{id}/approve',
        'POST /corrections/{id}/reject',
        'GET /duplicates',
        'GET /duplicates/merge-history',
        'GET /duplicates/{id}',
        'POST /duplicates/check',
        'POST /duplicates/{id}/merge',
        'POST /duplicates/{id}/not-duplicate',
        'POST /duplicates/{id}/defer',
        'GET /datasets',
        'GET /datasets/{id}',
        'PATCH /datasets/{id}',
        'POST /datasets/{id}/sync',
        'GET /datasets/{id}/sync-history',
        'GET /ai/usage/summary',
        'GET /ai/usage/costs',
        'GET /ai/usage/requests',
        'GET /ai/cache-performance',
        'GET /searches',
        'GET /search-intents',
        'GET /search-results-performance',
        'GET /sync-conflicts',
        'GET /sync-conflicts/{id}',
        'POST /sync-conflicts/{id}/resolve',
        'GET /facilities',
        'GET /facilities/{id}',
        'POST /facilities',
        'PATCH /facilities/{id}',
        'POST /facilities/{id}/publish',
        'POST /facilities/{id}/unpublish',
        'POST /facilities/{id}/archive',
        'POST /facilities/{id}/restore',
        'DELETE /facilities/{id}',
        'GET /facility-import-candidates',
        'GET /facility-import-candidates/{id}',
        'POST /facility-import-candidates/{id}/approve',
        'POST /facility-import-candidates/{id}/reject',
        'POST /facility-import-candidates/bulk-approve',
        'POST /facility-import-candidates/bulk-reject',
        'GET /provider-import-candidates',
        'GET /provider-import-candidates/{id}',
        'POST /provider-import-candidates/{id}/approve',
        'POST /provider-import-candidates/{id}/reject',
        'POST /provider-import-candidates/{id}/merge',
        'GET /feature-flags',
        'GET /ops/failed-emails',
        'GET /ops/failed-scheduled-tasks',
        'GET /categories',
        'GET /locations/states',
        'GET /locations/regions',
        'GET /locations/towns',
        'GET /audit',
        'GET /audit/{id}',
        'GET /search-gaps',
        'GET /overview',
        'GET /website-insights',
    ];

    private Router $router;

    protected function setUp(): void
    {
        parent::setUp();
        RequestContext::clear();
        Config::set('admin_api.enabled', true);
        Config::set('admin_api.restricted', true);
        Config::set('admin_api.mfa_required', false);
        Config::set('app.release', 'contract-test');

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

    public function testOpenApiListsPhase1Paths(): void
    {
        $yaml = (string) file_get_contents(base_path('docs/openapi/admin-v1.yaml'));
        preg_match_all('/^  (\/[^\s:]+):\s*$/m', $yaml, $matches);
        $paths = $matches[1] ?? [];
        if ($paths === []) {
            preg_match_all('/^  (\/[^\s:]+):/m', $yaml, $fallback);
            $paths = $fallback[1] ?? [];
        }
        self::assertNotEmpty($paths);

        foreach (['/health', '/audit', '/search-gaps', '/overview', '/website-insights', '/feature-flags', '/auth/mfa/challenge', '/auth/mfa/enroll/begin', '/auth/mfa/verify'] as $required) {
            self::assertContains($required, $paths, 'OpenAPI missing path ' . $required);
        }
    }

    public function testPhase1RequiredPathsAreRegisteredInRoutes(): void
    {
        $registered = $this->registeredRouteSignatures();
        $missing = [];
        foreach (self::PHASE1_REQUIRED_PATHS as $required) {
            if (!in_array($required, $registered, true)) {
                $missing[] = $required;
            }
        }

        self::assertSame([], $missing, 'Missing Admin API routes: ' . implode(', ', $missing));
    }

    public function testCapabilitiesMarksAuditAndSearchGapsActive(): void
    {
        $payload = $this->json($this->dispatch('GET', '/api/v1/admin/capabilities'));

        self::assertSame('read', $payload['data']['resources']['audit']);
        self::assertSame('read', $payload['data']['resources']['search_gaps']);
        self::assertSame('read', $payload['data']['resources']['overview']);
        self::assertSame('read', $payload['data']['resources']['website_insights']);
        self::assertFalse($payload['data']['mfa_required']);
    }

    public function testMockClientHealthAndVersionWithoutDatabase(): void
    {
        $health = $this->json($this->dispatch('GET', '/api/v1/admin/health'));
        self::assertSame('ok', $health['data']['status']);
        self::assertSame('v1', $health['data']['api_version']);

        $version = $this->json($this->dispatch('GET', '/api/v1/admin/version'));
        self::assertSame('v1', $version['data']['api_version']);
        self::assertSame('contract-test', $version['data']['release']);
    }

    public function testMockClientAuthValidationEndpointsFailClosedWithoutDatabase(): void
    {
        try {
            $this->dispatch('POST', '/api/v1/admin/auth/login', [], []);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            $response = Kernel::adminApiExceptionResponse($e);
            $payload = $this->json($response);
            self::assertSame(422, $response->status());
            self::assertSame('validation_failed', $payload['error']['code']);
            self::assertArrayHasKey('request_id', $payload['error']);
        }

        try {
            $this->dispatch('GET', '/api/v1/admin/auth/me');
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(401, $e->getStatusCode());
        }
    }

    public function testEnvelopeHelpersRemainStable(): void
    {
        RequestContext::begin(new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/v1/admin/health',
            'HTTP_X_REQUEST_ID' => 'ric-contract-0001',
        ], []));

        $data = AdminApiEnvelope::data(['ping' => true]);
        $payload = json_decode($data->content(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['ping' => true], $payload['data']);
        self::assertSame('ric-contract-0001', $data->headers()['X-Request-ID']);

        $error = AdminApiEnvelope::error('not_found', 'Missing.', 404);
        $errorPayload = json_decode($error->content(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('not_found', $errorPayload['error']['code']);
        self::assertSame('ric-contract-0001', $errorPayload['error']['request_id']);
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
