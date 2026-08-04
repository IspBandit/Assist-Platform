<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Api\AdminApiImportCandidateService;
use App\Services\Api\AdminApiScopes;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

final class AdminApiImportCandidateServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
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

    public function testListMethodsReturnSparseCollectionsWhenTablesUnavailable(): void
    {
        $source = (string) file_get_contents(
            (new ReflectionClass(AdminApiImportCandidateService::class))->getFileName()
        );
        self::assertStringContainsString("tableExists('traveller_facility_import_candidates')", $source);
        self::assertStringContainsString("tableExists('data_source_import_candidates')", $source);
        self::assertStringContainsString('catch (\Throwable)', $source);
        self::assertStringContainsString("'sparse' => true", $source);
        self::assertStringNotContainsString('reviewCandidate', $source);
        self::assertStringNotContainsString('/approve', $source);

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
