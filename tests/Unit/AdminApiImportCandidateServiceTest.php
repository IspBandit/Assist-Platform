<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Database;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Api\AdminApiContext;
use App\Services\Api\AdminApiImportCandidateService;
use App\Services\Api\AdminApiScopes;
use App\Services\Api\FacilityImportCandidateReviewGateway;
use App\Services\Api\ProviderImportCandidateReviewGateway;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AdminApiImportCandidateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        AdminApiContext::clear();
        parent::tearDown();
    }

    public function testImportCandidatesReadScopeIsRegisteredForRic(): void
    {
        self::assertContains('import_candidates:read', AdminApiScopes::ALL);
        self::assertContains('import_candidates:read', AdminApiScopes::RIC_SERVICE);
        self::assertNotContains('import_candidates:read', AdminApiScopes::NEVER_SERVICE);

        $catalog = AdminApiScopes::catalog();
        self::assertArrayHasKey('import_candidates:read', $catalog);
        self::assertTrue($catalog['import_candidates:read']['service']);
        self::assertStringContainsString('import-candidate', $catalog['import_candidates:read']['description']);
    }

    public function testImportCandidatesReviewScopeIsHumanOnly(): void
    {
        self::assertContains('import_candidates:review', AdminApiScopes::ALL);
        self::assertContains('import_candidates:review', AdminApiScopes::NEVER_SERVICE);
        self::assertNotContains('import_candidates:review', AdminApiScopes::RIC_SERVICE);

        $catalog = AdminApiScopes::catalog();
        self::assertArrayHasKey('import_candidates:review', $catalog);
        self::assertFalse($catalog['import_candidates:review']['service']);
        self::assertStringContainsString('provider', $catalog['import_candidates:review']['description']);
        self::assertStringContainsString('facility', $catalog['import_candidates:review']['description']);
    }

    public function testListMethodsReturnSparseCollectionsWhenTablesUnavailable(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(AdminApiImportCandidateService::class))->getFileName()
        );
        self::assertStringContainsString("tableExists('traveller_facility_import_candidates')", $source);
        self::assertStringContainsString("tableExists('data_source_import_candidates')", $source);
        self::assertStringContainsString('catch (\Throwable)', $source);
        self::assertStringContainsString("'sparse' => true", $source);
        self::assertStringContainsString('reviewCandidate', $source);
        self::assertStringContainsString('approveFacilityCandidate', $source);
        self::assertStringContainsString('rejectFacilityCandidate', $source);
        self::assertStringContainsString('bulkApproveFacilityCandidates', $source);
        self::assertStringContainsString('bulkRejectFacilityCandidates', $source);
        self::assertStringContainsString('approveProviderCandidate', $source);
        self::assertStringContainsString('rejectProviderCandidate', $source);

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
        $service = new AdminApiImportCandidateService();
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/api/v1/admin/facility-import-candidates',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        $facility = $service->listFacilityCandidates($request);
        self::assertSame([], $facility['items']);
        self::assertTrue($facility['meta']['sparse']);
        self::assertSame('traveller_facility_import_candidates_missing', $facility['meta']['source']);
        self::assertFalse($facility['meta']['has_more']);
        self::assertNull($facility['links']['next']);

        $provider = $service->listProviderCandidates($request);
        self::assertSame([], $provider['items']);
        self::assertTrue($provider['meta']['sparse']);
        self::assertSame('data_source_import_candidates_missing', $provider['meta']['source']);
    }

    public function testApproveFacilityCandidateWiresReviewerAndNotesToGovernmentDatasetService(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 42, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-1');

        $gateway = new class implements FacilityImportCandidateReviewGateway {
            /** @var list<array{id:int,action:string,reviewer:?int,notes:?string}> */
            public array $calls = [];

            public function reviewCandidate(int $candidateId, string $action, ?int $reviewerId = null, ?string $notes = null): void
            {
                $this->calls[] = [
                    'id' => $candidateId,
                    'action' => $action,
                    'reviewer' => $reviewerId,
                    'notes' => $notes,
                ];
            }
        };

        $service = new class ($gateway) extends AdminApiImportCandidateService {
            private int $phase = 0;

            public function __construct(FacilityImportCandidateReviewGateway $gateway)
            {
                parent::__construct($gateway);
            }

            public function showFacilityCandidate(int $id): array
            {
                $this->phase++;

                return [
                    'id' => (string) $id,
                    'review_status' => $this->phase === 1 ? 'pending' : 'approved',
                    'facility_id' => $this->phase === 1 ? null : '99',
                    'name' => 'Rest stop',
                ];
            }
        };

        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/facility-import-candidates/7/approve',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        $result = $service->approveFacilityCandidate(7, ['reason' => 'Verified against Toilet Map'], $request);

        self::assertSame('approved', $result['review_status']);
        self::assertSame('99', $result['facility_id']);
        self::assertCount(1, $gateway->calls);
        self::assertSame(7, $gateway->calls[0]['id']);
        self::assertSame('approve', $gateway->calls[0]['action']);
        self::assertSame(42, $gateway->calls[0]['reviewer']);
        self::assertSame('Verified against Toilet Map', $gateway->calls[0]['notes']);
    }

    public function testRejectFacilityCandidateRequiresPendingStatus(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 7, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-2');

        $service = new class extends AdminApiImportCandidateService {
            public function showFacilityCandidate(int $id): array
            {
                return [
                    'id' => (string) $id,
                    'review_status' => 'approved',
                    'facility_id' => '12',
                    'name' => 'Rest stop',
                ];
            }
        };

        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/facility-import-candidates/3/reject',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        try {
            $service->rejectFacilityCandidate(3, [], $request);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(409, $e->getStatusCode());
            self::assertSame('conflict', $e->errorCode());
        }
    }

    public function testBulkApproveFacilityCandidatesReturnsPerIdResults(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 42, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-bulk');

        $service = new class extends AdminApiImportCandidateService {
            /** @var list<int> */
            public array $approved = [];

            public function approveFacilityCandidate(int $id, array $input, Request $request): array
            {
                if ($id === 2) {
                    throw new AdminApiException(409, 'conflict', 'Only pending facility import candidates can be reviewed.');
                }
                $this->approved[] = $id;

                return [
                    'id' => (string) $id,
                    'review_status' => 'approved',
                    'facility_id' => (string) (100 + $id),
                ];
            }
        };

        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/facility-import-candidates/bulk-approve',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        $result = $service->bulkApproveFacilityCandidates([
            'ids' => [1, 2, 1, 3],
            'reason' => 'Batch verified',
        ], $request);

        self::assertSame('bulk_approve', $result['action']);
        self::assertSame(3, $result['count']);
        self::assertSame(2, $result['processed']);
        self::assertSame(1, $result['failed']);
        self::assertSame([1, 3], $service->approved);
        self::assertSame('approved', $result['results'][0]['status']);
        self::assertSame('failed', $result['results'][1]['status']);
        self::assertSame('conflict', $result['results'][1]['error']['code']);
        self::assertSame('3', $result['results'][2]['id']);
    }

    public function testBulkRejectFacilityCandidatesRequiresIds(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 7, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-bulk-2');

        $service = new AdminApiImportCandidateService();
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/facility-import-candidates/bulk-reject',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        try {
            $service->bulkRejectFacilityCandidates([], $request);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('ids', $e->fields());
        }
    }

    public function testApproveProviderCandidateRequiresRetentionAndEvidenceThenDelegates(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 42, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-3');

        $gateway = new class implements ProviderImportCandidateReviewGateway {
            /** @var list<array{decision:string,retention:bool,evidence:string,category:?int,notes:string}> */
            public array $calls = [];

            public function review(
                int $candidateId,
                int $brandId,
                string $decision,
                ?int $providerId,
                int $userId,
                bool $retentionConfirmed = false,
                ?int $categoryId = null,
                string $evidenceUrl = '',
                string $reviewNotes = ''
            ): int {
                $this->calls[] = [
                    'decision' => $decision,
                    'retention' => $retentionConfirmed,
                    'evidence' => $evidenceUrl,
                    'category' => $categoryId,
                    'notes' => $reviewNotes,
                    'user' => $userId,
                    'candidate' => $candidateId,
                    'brand' => $brandId,
                ];

                return 55;
            }
        };

        $service = new class (null, $gateway) extends AdminApiImportCandidateService {
            private int $phase = 0;

            public function __construct(?FacilityImportCandidateReviewGateway $datasets, ProviderImportCandidateReviewGateway $providers)
            {
                parent::__construct($datasets, $providers);
            }

            public function showProviderCandidate(int $id): array
            {
                $this->phase++;

                return [
                    'id' => (string) $id,
                    'review_status' => $this->phase === 1 ? 'pending' : 'approved',
                    'evidence_status' => $this->phase === 1 ? 'required' : 'confirmed',
                    'provider_id' => $this->phase === 1 ? null : '55',
                    'business_name' => 'Van Care',
                ];
            }
        };

        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/provider-import-candidates/9/approve',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        $result = $service->approveProviderCandidate(9, [
            'retention_confirmed' => true,
            'evidence_url' => 'https://example.test/business',
            'category_id' => 7,
            'reason' => 'Website matches listing',
        ], $request);

        self::assertSame('approved', $result['review_status']);
        self::assertSame('55', $result['provider_id']);
        self::assertCount(2, $gateway->calls);
        self::assertSame('confirm', $gateway->calls[0]['decision']);
        self::assertSame('approve', $gateway->calls[1]['decision']);
        self::assertSame(42, $gateway->calls[1]['user']);
        self::assertSame('https://example.test/business', $gateway->calls[1]['evidence']);
        self::assertSame(7, $gateway->calls[1]['category']);
        self::assertTrue($gateway->calls[1]['retention']);
    }

    public function testApproveProviderCandidateValidatesRetentionAndEvidence(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 7, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-4');

        $service = new AdminApiImportCandidateService();
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/provider-import-candidates/3/approve',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        try {
            $service->approveProviderCandidate(3, [], $request);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(422, $e->getStatusCode());
            self::assertArrayHasKey('retention_confirmed', $e->fields());
            self::assertArrayHasKey('evidence_url', $e->fields());
        }
    }

    public function testRejectProviderCandidateRequiresPendingOrHeld(): void
    {
        BrandContext::set($this->vanAssistBrand());
        AdminApiContext::setUser(['id' => 7, 'email' => 'admin@example.test'], ['import_candidates:review'], 'token-5');

        $service = new class extends AdminApiImportCandidateService {
            public function showProviderCandidate(int $id): array
            {
                return [
                    'id' => (string) $id,
                    'review_status' => 'approved',
                    'provider_id' => '12',
                    'business_name' => 'Van Care',
                ];
            }
        };

        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/api/v1/admin/provider-import-candidates/3/reject',
            'REMOTE_ADDR' => '127.0.0.1',
        ], []);

        try {
            $service->rejectProviderCandidate(3, [], $request);
            self::fail('Expected AdminApiException');
        } catch (AdminApiException $e) {
            self::assertSame(409, $e->getStatusCode());
            self::assertSame('conflict', $e->errorCode());
        }
    }

    public function testMappersOmitRawJsonFromSummariesAndSanitiseDetailRaw(): void
    {
        $service = new AdminApiImportCandidateService();

        $facilitySummary = $this->invoke($service, 'facilitySummary', [[
            'id' => 9,
            'job_id' => 3,
            'dataset_id' => 2,
            'dataset_title' => 'Toilet Map',
            'publisher' => 'DFAT',
            'external_id' => 'ext-1',
            'facility_type' => 'toilet',
            'name' => 'Rest stop',
            'formatted_address' => '1 Main St',
            'locality' => 'Brisbane',
            'latitude' => '-27.5',
            'longitude' => '153.0',
            'confidence' => 80,
            'review_status' => 'pending',
            'duplicate_facility_id' => null,
            'facility_id' => null,
            'created_at' => '2026-08-01 10:00:00',
            'expires_at' => '2026-09-01 10:00:00',
            'raw_json' => '{"api_key":"secret","name":"Rest stop"}',
        ]]);

        self::assertSame('9', $facilitySummary['id']);
        self::assertSame('Rest stop', $facilitySummary['name']);
        self::assertArrayNotHasKey('raw_json', $facilitySummary);
        self::assertArrayNotHasKey('raw', $facilitySummary);

        $facilityDetail = $this->invoke($service, 'facilityDetail', [[
            'id' => 9,
            'job_id' => 3,
            'dataset_id' => 2,
            'dataset_title' => 'Toilet Map',
            'publisher' => 'DFAT',
            'brand_id' => 1,
            'external_id' => 'ext-1',
            'facility_type' => 'toilet',
            'name' => 'Rest stop',
            'formatted_address' => '1 Main St',
            'locality' => 'Brisbane',
            'latitude' => '-27.5',
            'longitude' => '153.0',
            'source_url' => 'https://example.test',
            'source_licence' => 'CC',
            'source_attribution' => 'Gov',
            'confidence' => 80,
            'review_status' => 'pending',
            'duplicate_facility_id' => null,
            'facility_id' => null,
            'review_notes' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => null,
            'expires_at' => '2026-09-01 10:00:00',
            'raw_json' => json_encode([
                'api_key' => 'should-hide',
                'name' => 'Rest stop',
                'nested' => ['token' => 'abc', 'ok' => true],
            ], JSON_THROW_ON_ERROR),
        ]]);

        self::assertSame('[redacted]', $facilityDetail['raw']['api_key']);
        self::assertSame('Rest stop', $facilityDetail['raw']['name']);
        self::assertSame('[redacted]', $facilityDetail['raw']['nested']['token']);
        self::assertTrue($facilityDetail['raw']['nested']['ok']);

        $providerSummary = $this->invoke($service, 'providerSummary', [[
            'id' => 4,
            'job_id' => 1,
            'connector_id' => 2,
            'connector_key' => 'places',
            'connector_name' => 'Places',
            'category_id' => 7,
            'category_name' => 'Mechanic',
            'external_id' => 'p-1',
            'business_name' => 'Van Care',
            'formatted_address' => '2 Side St',
            'phone' => '0400000000',
            'website' => 'https://example.test',
            'latitude' => null,
            'longitude' => null,
            'candidate_state' => 'QLD',
            'route_hub' => null,
            'confidence' => 90,
            'review_status' => 'pending',
            'duplicate_provider_id' => 11,
            'duplicate_name' => 'Van Care Pty',
            'duplicate_score' => 88,
            'provider_id' => null,
            'evidence_status' => 'required',
            'created_at' => '2026-08-01 10:00:00',
            'expires_at' => '2026-09-01 10:00:00',
            'raw_json' => '{"secret":"nope"}',
        ]]);

        self::assertSame('Van Care', $providerSummary['business_name']);
        self::assertSame('QLD', $providerSummary['candidate_state']);
        self::assertArrayNotHasKey('raw_json', $providerSummary);
        self::assertArrayNotHasKey('raw', $providerSummary);
    }

    /**
     * @param list<mixed> $args
     * @return mixed
     */
    private function invoke(object $service, string $method, array $args): mixed
    {
        $ref = new ReflectionMethod($service, $method);
        $ref->setAccessible(true);

        return $ref->invokeArgs($service, $args);
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
