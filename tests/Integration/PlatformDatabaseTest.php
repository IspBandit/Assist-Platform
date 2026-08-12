<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Config;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimit as RateLimitMiddleware;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\EmailQueue;
use App\Services\EmailSuppression;
use App\Services\Mailer;
use App\Services\LaunchReadinessService;
use App\Services\InvoiceExportService;
use App\Services\PlatformBackfill;
use App\Services\RateLimiter;
use App\Services\RegulatoryAlertService;
use App\Services\TownCoordinateActivation;
use App\Services\CampaignMetrics;
use App\Services\DataSourceService;
use App\Services\NationalRouteImportService;
use App\Models\GarageAsset;
use App\Models\Provider;
use App\Services\Api\AdminApiFacilityService;
use App\Platform\AiSearch\Adapters\StayFacilitySearchBridge;
use App\Services\RoadDistance\GoogleRoutesCredentialProvisioner;
use App\Services\RoadDistance\GoogleRoutesCredentialResolver;
use PHPUnit\Framework\TestCase;

final class PlatformDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        if (getenv('RUN_INTEGRATION_TESTS') !== '1') {
            $this->markTestSkipped('Set RUN_INTEGRATION_TESTS=1 with a disposable database');
        }
    }

    public function testMigrationHistoryIsCleanAndChecksummed(): void
    {
        $dirty = (int) Database::scalar("SELECT COUNT(*) FROM migrations WHERE status <> 'succeeded'");
        $missingChecksums = (int) Database::scalar(
            "SELECT COUNT(*) FROM migrations WHERE checksum IS NULL OR CHAR_LENGTH(checksum) <> 64"
        );

        self::assertSame(0, $dirty);
        self::assertSame(0, $missingChecksums);
    }

    public function testAuthoritativeLocalTorquePackIsImportedWithSafeRouting(): void
    {
        self::assertTrue(Database::tableExists('provider_source_records'));
        self::assertSame(9730, (int) Database::scalar('SELECT COUNT(*) FROM provider_source_records'));
        self::assertSame(3108, (int) Database::scalar(
            "SELECT COUNT(*) FROM provider_source_records WHERE payload_json LIKE '%\"fuel-station\"%'"
        ));
        self::assertSame(0, (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_brand_category_assignments a '
            . 'JOIN brand_provider_categories c ON c.id=a.category_id '
            . 'JOIN brands b ON b.id=c.brand_id '
            . "WHERE c.category_key IN ('fuel-station','ev-charging') "
            . "AND b.brand_key NOT IN ('localtorque','vanassist')"
        ));
        self::assertSame(0, (int) Database::scalar(
            'SELECT COUNT(DISTINCT l.id) FROM provider_source_records psr '
            . 'JOIN providers p ON p.id=psr.provider_id JOIN provider_brand_listings l ON l.provider_id=p.id '
            . "WHERE p.is_unclaimed=1 AND psr.needs_review=1 AND l.status='active' AND l.search_visible=1 "
            . 'AND NOT EXISTS (SELECT 1 FROM provider_source_records good WHERE good.provider_id=p.id '
            . 'AND good.publishable=1 AND good.needs_review=0)'
        ));
        self::assertSame(0, (int) Database::scalar(
            'SELECT COUNT(DISTINCT psr.id) FROM provider_source_records psr '
            . 'JOIN providers p ON p.id=psr.provider_id JOIN towns t ON t.id=p.base_town_id '
            . 'JOIN provider_brand_listings l ON l.provider_id=p.id '
            . "WHERE p.is_unclaimed=1 AND p.status='active' AND l.status='active' AND l.search_visible=1 "
            . 'AND psr.publishable=1 AND psr.needs_review=0 '
            . "AND JSON_TYPE(JSON_EXTRACT(psr.payload_json,'$.lat')) IN ('INTEGER','DOUBLE') "
            . "AND JSON_TYPE(JSON_EXTRACT(psr.payload_json,'$.lng')) IN ('INTEGER','DOUBLE') "
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL '
            . 'AND (6371 * ACOS(LEAST(1,GREATEST(-1, '
            . "COS(RADIANS(CAST(JSON_UNQUOTE(JSON_EXTRACT(psr.payload_json,'$.lat')) AS DECIMAL(10,6)))) "
            . '* COS(RADIANS(t.latitude)) '
            . "* COS(RADIANS(t.longitude)-RADIANS(CAST(JSON_UNQUOTE(JSON_EXTRACT(psr.payload_json,'$.lng')) AS DECIMAL(10,6)))) "
            . "+ SIN(RADIANS(CAST(JSON_UNQUOTE(JSON_EXTRACT(psr.payload_json,'$.lat')) AS DECIMAL(10,6)))) "
            . '* SIN(RADIANS(t.latitude)))))) > 150'
        ), 'Public source coordinates must not contradict the displayed Australian town by more than 150 km.');

        $fuelCategoryId = (int) Database::scalar(
            "SELECT id FROM service_categories WHERE slug='fuel-and-travel-stops'"
        );
        $nearGladstone = Provider::forCategoryNear($fuelCategoryId, -23.842, 151.255, 150);
        self::assertNotEmpty($nearGladstone);
        self::assertLessThanOrEqual(150.0, (float) $nearGladstone[0]['distance_km']);
    }

    public function testProviderRadiusSearchNeverBuildsAHybridCoordinate(): void
    {
        $row = Database::selectOne(
            'SELECT p.id, ps.category_id, t.latitude, t.longitude '
            . 'FROM providers p JOIN provider_services ps ON ps.provider_id=p.id '
            . 'JOIN towns t ON t.id=p.base_town_id '
            . "WHERE p.status='active' AND p.deleted_at IS NULL "
            . "AND t.coordinate_confidence IN ('authoritative','statistical') "
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL LIMIT 1'
        );
        self::assertNotNull($row);

        Database::beginTransaction();
        try {
            Database::query('UPDATE providers SET latitude=0,longitude=NULL WHERE id=?', [(int) $row['id']]);
            $matches = Provider::forCategoryNear(
                (int) $row['category_id'],
                (float) $row['latitude'],
                (float) $row['longitude'],
                25,
                500,
            );
            $match = null;
            foreach ($matches as $candidate) {
                if ((int) $candidate['id'] === (int) $row['id']) {
                    $match = $candidate;
                    break;
                }
            }

            self::assertNotNull($match, 'A partial provider point must fall back to its trusted town centre.');
            self::assertSame('town_centre', $match['distance_basis']);
            self::assertEqualsWithDelta((float) $row['latitude'], (float) $match['town_lat'], 0.0000001);
            self::assertEqualsWithDelta((float) $row['longitude'], (float) $match['town_lng'], 0.0000001);
        } finally {
            Database::rollBack();
        }
    }

    public function testTravellerFacilityDetailHonoursSelectedBrandScope(): void
    {
        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        BrandContext::set($registry->get('vanassist'));
        $otherBrandId = (int) Database::scalar("SELECT id FROM brands WHERE brand_key='towsmart'");

        Database::beginTransaction();
        try {
            $slug = 'scope-test-' . bin2hex(random_bytes(6));
            $otherId = Database::insert(
                'INSERT INTO traveller_facilities (facility_type,name,slug,verification_status,status,confidence,brand_id,created_at,updated_at) '
                . "VALUES ('dump_point','Other brand facility',?,'verified','active',100,?,NOW(),NOW())",
                [$slug, $otherBrandId]
            );

            try {
                (new AdminApiFacilityService())->show($otherId);
                self::fail('A facility from another brand must not be readable by guessed ID.');
            } catch (AdminApiException $e) {
                self::assertSame(404, $e->getStatusCode());
            }

            $sharedId = Database::insert(
                'INSERT INTO traveller_facilities (facility_type,name,slug,verification_status,status,confidence,brand_id,created_at,updated_at) '
                . "VALUES ('dump_point','Shared facility',?,'verified','active',100,NULL,NOW(),NOW())",
                [$slug . '-shared']
            );
            self::assertSame((string) $sharedId, (new AdminApiFacilityService())->show($sharedId)['id']);
        } finally {
            Database::rollBack();
            BrandContext::clear();
        }
    }

    public function testApprovedStayFacilityEvidenceAppearsInAskWithinRadius(): void
    {
        $stateId = (int) Database::scalar("SELECT id FROM states WHERE abbreviation='QLD' LIMIT 1");
        self::assertGreaterThan(0, $stateId);

        Database::beginTransaction();
        try {
            $slug = 'ask-stay-facility-' . bin2hex(random_bytes(5));
            $parkId = Database::insert(
                'INSERT INTO caravan_parks '
                . '(name,slug,state_id,latitude,longitude,public_page_enabled,status,stay_type,price_type,created_at,updated_at) '
                . "VALUES ('Griffiths Creek test camping area',?,?, -24.35,151.10,1,'active','national_park','unknown',NOW(),NOW())",
                [$slug, $stateId]
            );
            Database::insert(
                'INSERT INTO stay_facility_claims '
                . '(park_id,facility_type,facility_status,facility_value,details,source_type,source_name,source_confidence,source_specificity,verified_at,last_seen_at,created_at,updated_at) '
                . "VALUES (?,'dump_point','yes','portable_toilet_waste_disposal','Portable waste disposal is available.','government','Queensland Parks',100,'facility',NOW(),NOW(),NOW(),NOW())",
                [$parkId]
            );

            $results = (new StayFacilitySearchBridge())->search(['dump_point'], -24.35, 151.10, 25);
            $result = array_values(array_filter($results, static fn (array $row): bool => (int) ($row['stay_id'] ?? 0) === $parkId));

            self::assertCount(1, $result);
            self::assertSame('dump_point', $result[0]['facility_type']);
            self::assertSame('yes', $result[0]['facility_status']);
            self::assertLessThanOrEqual(25.0, (float) $result[0]['distance_km']);
        } finally {
            Database::rollBack();
        }
    }

    public function testLaunchGateProducesAllFourEvidenceGroups(): void
    {
        $readiness = LaunchReadinessService::inspect();
        self::assertContains($readiness['status'], ['pass', 'warning', 'fail']);
        self::assertSame(
            ['data_trust', 'search_reliability', 'compliant_outreach', 'operational_proof'],
            array_keys($readiness['groups'])
        );
        foreach ($readiness['groups'] as $group) {
            self::assertNotEmpty($group['checks']);
            self::assertContains($group['status'], ['pass', 'warning', 'fail']);
        }
    }

    public function testPlatformBrandsAndBackfillIntegrity(): void
    {
        $brands = Database::select('SELECT id, brand_key, status FROM brands ORDER BY id');
        self::assertSame(['vanassist', 'towsmart', 'trailerwise', 'localtorque', 'polaris'], array_column($brands, 'brand_key'));
        self::assertSame('active', $brands[0]['status']);
        self::assertSame('private', $brands[4]['status']);

        foreach ((new PlatformBackfill())->validate() as $check) {
            self::assertTrue($check['valid'], "Backfill count {$check['actual']} did not match {$check['expected']}");
        }
    }

    public function testUnifiedAdministrationSchemaAndRolesAreInstalled(): void
    {
        self::assertTrue(Database::tableExists('admin_brand_handoff_tokens'));
        foreach (['template_key', 'campaign_name'] as $column) {
            self::assertSame(1, (int) Database::scalar(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
                ['social_media_assets', $column]
            ));
        }

        $roles = array_column(Database::select(
            "SELECT slug FROM roles WHERE slug IN ('super-administrator','platform-administrator','brand-administrator','moderator','editor','support','finance','marketing')"
        ), 'slug');
        sort($roles);
        $expected = ['brand-administrator', 'editor', 'finance', 'marketing', 'moderator', 'platform-administrator', 'super-administrator', 'support'];
        sort($expected);
        self::assertSame($expected, $roles);

        self::assertGreaterThan(0, (int) Database::scalar(
            "SELECT COUNT(*) FROM role_permissions rp INNER JOIN roles r ON r.id = rp.role_id WHERE r.slug = 'brand-administrator'"
        ));
    }

    public function testDataSourceSchemaAndPlatformPermissionsAreInstalled(): void
    {
        foreach (['data_source_connectors','data_source_credentials','data_source_category_mappings','data_source_import_jobs','data_source_import_candidates','data_source_usage_daily','data_source_schedules'] as $table) {
            self::assertTrue(Database::tableExists($table), $table.' was not installed');
        }
        self::assertSame(1,(int)Database::scalar("SELECT COUNT(*) FROM data_source_connectors WHERE connector_key='google_places'"));
        self::assertSame(4,(int)Database::scalar("SELECT COUNT(*) FROM permissions WHERE slug LIKE 'data_sources.%'"));
        self::assertGreaterThanOrEqual(4,(int)Database::scalar("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='platform-administrator' AND p.slug LIKE 'data_sources.%'"));
        self::assertSame(1,(int)Database::scalar("SELECT COUNT(*) FROM data_source_connectors WHERE connector_key='national_route_places'"));
        foreach (['candidate_state','route_hub','evidence_status','evidence_url','review_notes','hold_reason'] as $column) {
            self::assertSame(1, (int)Database::scalar(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'data_source_import_candidates\' AND column_name=?',
                [$column]
            ));
        }
        self::assertSame(5,(int)Database::scalar(
            "SELECT COUNT(*) FROM brand_provider_categories WHERE brand_id=1 AND category_key IN ('caravan-gas-appliances','trailer-brakes-suspension','mobile-diesel-mechanics','fuel-travel-stops','ev-charging')"
        ));
    }

    public function testProtectedRoutesCredentialIsEncryptedAndResolvable(): void
    {
        $apiKey = 'AIza' . str_repeat('R', 35);
        try {
            (new GoogleRoutesCredentialProvisioner())->provision($apiKey);

            $stored = (string) Database::scalar(
                "SELECT cr.encrypted_value
                 FROM data_source_credentials cr
                 JOIN data_source_connectors c ON c.id = cr.connector_id
                 WHERE c.connector_key = 'google_routes' AND cr.credential_key = 'api_key'"
            );
            self::assertStringStartsWith('enc:v1:', $stored);
            self::assertStringNotContainsString($apiKey, $stored);
            self::assertSame(
                ['key' => $apiKey, 'source' => 'encrypted_google_routes_connector'],
                (new GoogleRoutesCredentialResolver())->resolve()
            );
        } finally {
            Database::query(
                "DELETE cr FROM data_source_credentials cr
                 JOIN data_source_connectors c ON c.id = cr.connector_id
                 WHERE c.connector_key = 'google_routes'"
            );
            Database::query("DELETE FROM data_source_connectors WHERE connector_key = 'google_routes'");
        }
    }

    public function testNationalRouteCandidateRequiresIndependentEvidenceBeforeApproval(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'national-route-');
        self::assertNotFalse($source);
        $jsonl = $source . '.jsonl';
        rename($source, $jsonl);
        file_put_contents($jsonl, json_encode([
            'external_id'=>'places:integration-national-route-evidence',
            'google_place_id'=>'integration-national-route-evidence',
            'business_name'=>'Integration Highway Fuel',
            'formatted_address'=>'1 Test Highway, Dubbo NSW',
            'phone'=>'02 1234 5678',
            'website'=>'https://example.test/fuel',
            'latitude'=>-32.25,
            'longitude'=>148.60,
            'business_status'=>'OPERATIONAL',
            'place_types'=>['gas_station'],
            'category_slugs'=>['fuel-station'],
            'route_hubs'=>['Dubbo, NSW'],
            'discovery_queries'=>['fuel station diesel'],
            'state'=>'NSW',
        ], JSON_THROW_ON_ERROR) . "\n\n{malformed-json\n");

        $jobId = 0;
        $providerId = 0;
        $reviewerId = 0;
        try {
            $import = new NationalRouteImportService();
            $jobId = $import->stageLocalFile($jsonl, 1);
            $result = $import->processJob($jobId, 1, 10);
            self::assertTrue($result['done']);
            self::assertSame(3, $result['processed']);
            self::assertSame(1, $result['inserted']);
            self::assertSame(1, $result['skipped']);
            $jobScope = json_decode((string)Database::scalar('SELECT scope_json FROM data_source_import_jobs WHERE id=?',[$jobId]), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(3, $jobScope['processed_lines']);
            self::assertSame(1, $jobScope['skipped_lines']);
            self::assertNotEmpty($jobScope['errors']);
            $candidate = Database::selectOne(
                'SELECT c.*,b.category_key FROM data_source_import_candidates c LEFT JOIN brand_provider_categories b ON b.id=c.category_id WHERE c.job_id=?',
                [$jobId]
            );
            self::assertNotNull($candidate);
            self::assertSame('required', $candidate['evidence_status']);
            self::assertSame('pending', $candidate['review_status']);
            self::assertSame('NSW', $candidate['candidate_state']);
            self::assertSame('fuel-travel-stops', $candidate['category_key']);
            self::assertSame('national_route_places', Database::scalar(
                'SELECT connector_key FROM data_source_connectors WHERE id=?',
                [$candidate['connector_id']]
            ));
            $reviewerId = Database::insert(
                "INSERT INTO users (name,email,password_hash,status,created_at) VALUES ('Route Reviewer',?,'test','active',NOW())",
                ['route-review-' . bin2hex(random_bytes(5)) . '@example.test']
            );

            $crossBrandBlocked = false;
            try {
                (new DataSourceService())->review((int)$candidate['id'], 2, 'hold', null, 1);
            } catch (\RuntimeException) {
                $crossBrandBlocked = true;
            }
            self::assertTrue($crossBrandBlocked, 'A candidate must not be reviewable outside its active brand workspace.');

            (new DataSourceService())->review((int)$candidate['id'], 1, 'hold', null, $reviewerId, false, (int)$candidate['category_id'], 'https://maps.google.com/example');
            self::assertSame('held', Database::scalar('SELECT review_status FROM data_source_import_candidates WHERE id=?',[$candidate['id']]));
            (new DataSourceService())->review((int)$candidate['id'], 1, 'restore', null, $reviewerId);

            $blockedMessage = '';
            try {
                (new DataSourceService())->review((int)$candidate['id'], 1, 'approve', null, 1, true, (int)$candidate['category_id']);
            } catch (\RuntimeException $exception) {
                $blockedMessage = $exception->getMessage();
            }
            self::assertStringContainsString('independent source', $blockedMessage);
            self::assertSame('pending', Database::scalar(
                'SELECT review_status FROM data_source_import_candidates WHERE id=?',
                [$candidate['id']]
            ));

            $providerId = (new DataSourceService())->review(
                (int)$candidate['id'], 1, 'approve', null, $reviewerId, true,
                (int)$candidate['category_id'], 'https://example.test/fuel', 'Fuel service confirmed on the independent business page.'
            );
            self::assertGreaterThan(0, $providerId);
            self::assertSame('approved', Database::scalar('SELECT review_status FROM data_source_import_candidates WHERE id=?',[$candidate['id']]));
            self::assertSame('confirmed', Database::scalar('SELECT evidence_status FROM data_source_import_candidates WHERE id=?',[$candidate['id']]));
            self::assertSame('admin_verified', Database::scalar('SELECT verification_status FROM provider_discovery_evidence WHERE provider_id=?',[$providerId]));
            self::assertSame(1, (int)Database::scalar(
                "SELECT COUNT(*) FROM provider_services ps JOIN service_categories sc ON sc.id=ps.category_id WHERE ps.provider_id=? AND sc.slug='fuel-and-travel-stops'",
                [$providerId]
            ));
            self::assertSame('-32.2500000', (string)Database::scalar('SELECT latitude FROM providers WHERE id=?',[$providerId]));
            self::assertNotEmpty(\App\Models\Provider::forCategoryNear(
                (int)Database::scalar("SELECT id FROM service_categories WHERE slug='fuel-and-travel-stops'"),
                -32.25, 148.60, 10
            ));
            Database::query('UPDATE data_source_import_candidates SET expires_at=DATE_SUB(NOW(),INTERVAL 1 DAY) WHERE id=?',[$candidate['id']]);
            self::assertSame(1, $import->purgeExpiredCandidates(), 'Approved Google-derived candidate details must also expire.');
            self::assertSame(0, (int)Database::scalar('SELECT COUNT(*) FROM data_source_import_candidates WHERE id=?',[$candidate['id']]));
        } finally {
            if ($providerId > 0) Database::query('DELETE FROM providers WHERE id=?', [$providerId]);
            if ($jobId > 0) Database::query('DELETE FROM data_source_import_jobs WHERE id=?', [$jobId]);
            if ($reviewerId > 0) Database::query('DELETE FROM users WHERE id=?', [$reviewerId]);
            @unlink($jsonl);
        }
    }

    public function testDataIntelligenceSchemaAndPlatformPermissionsAreInstalled(): void
    {
        foreach (['data_intelligence_sources','locality_population_statistics','data_intelligence_tasks'] as $table) {
            self::assertTrue(Database::tableExists($table), $table.' was not installed');
        }
        self::assertSame(1,(int)Database::scalar("SELECT COUNT(*) FROM data_intelligence_sources WHERE source_key='provider_coverage'"));
        self::assertSame(2,(int)Database::scalar("SELECT COUNT(*) FROM permissions WHERE slug LIKE 'data_intelligence.%'"));
        self::assertGreaterThanOrEqual(2,(int)Database::scalar("SELECT COUNT(*) FROM role_permissions rp JOIN roles r ON r.id=rp.role_id JOIN permissions p ON p.id=rp.permission_id WHERE r.slug='platform-administrator' AND p.slug LIKE 'data_intelligence.%'"));
    }

    public function testRegulatoryLibraryCoversEveryBrandAndJurisdiction(): void
    {
        foreach (['regulatory_authorities', 'regulatory_documents', 'regulatory_document_brands', 'regulatory_source_checks'] as $table) {
            self::assertTrue(Database::tableExists($table), $table . ' was not installed');
        }
        self::assertSame(
            ['ACT', 'AUS', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'],
            array_column(Database::select('SELECT DISTINCT jurisdiction_code FROM regulatory_documents ORDER BY jurisdiction_code'), 'jurisdiction_code')
        );
        self::assertSame(
            [1, 2, 3, 4],
            array_map('intval', array_column(Database::select('SELECT DISTINCT brand_id FROM regulatory_document_brands ORDER BY brand_id'), 'brand_id'))
        );
        self::assertGreaterThanOrEqual(30, (int) Database::scalar('SELECT COUNT(*) FROM regulatory_documents'));
        self::assertGreaterThanOrEqual(8, (int) Database::scalar("SELECT COUNT(*) FROM regulatory_documents WHERE download_url IS NOT NULL"));
        self::assertSame(0, (int) Database::scalar(
            "SELECT COUNT(*) FROM regulatory_documents WHERE is_public=1 AND official_document<>1"
        ));
    }

    public function testMotorsportLibraryHasCompleteTaxonomyRulesAndVenueLayers(): void
    {
        foreach (['motorsport_authorities','motorsport_families','motorsport_disciplines','motorsport_documents','motorsport_document_families','motorsport_venues','motorsport_venue_families','motorsport_source_checks'] as $table) {
            self::assertTrue(Database::tableExists($table), $table . ' was not installed');
        }
        self::assertSame(9, (int) Database::scalar('SELECT COUNT(*) FROM motorsport_families'));
        self::assertGreaterThanOrEqual(55, (int) Database::scalar('SELECT COUNT(*) FROM motorsport_disciplines'));
        self::assertGreaterThanOrEqual(15, (int) Database::scalar("SELECT COUNT(*) FROM motorsport_documents WHERE is_public=1 AND publication_status='current'"));
        self::assertSame(0, (int) Database::scalar('SELECT COUNT(*) FROM motorsport_families f WHERE NOT EXISTS (SELECT 1 FROM motorsport_document_families df WHERE df.family_key=f.family_key)'));
        self::assertSame(0, (int) Database::scalar('SELECT COUNT(*) FROM motorsport_families f WHERE NOT EXISTS (SELECT 1 FROM motorsport_venue_families vf WHERE vf.family_key=f.family_key)'));
        self::assertSame(0, (int) Database::scalar('SELECT COUNT(*) FROM motorsport_venues WHERE website_url IS NULL AND calendar_url IS NULL'));
    }

    public function testSharedGarageSchemaUsesUserOwnershipInsteadOfBrandIsolation(): void
    {
        foreach (['garage_assets', 'garage_documents', 'garage_reminder_preferences', 'garage_brand_activity'] as $table) {
            self::assertTrue(Database::tableExists($table), $table . ' was not installed');
        }

        $columns = array_column(Database::select(
            "SELECT column_name AS name FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='garage_assets'"
        ), 'name');
        self::assertContains('user_id', $columns);
        self::assertContains('created_in_brand_id', $columns);
        self::assertNotContains('brand_id', $columns, 'Garage assets must not be siloed to the brand where they were created');
        self::assertNotContains('vin', $columns);
        self::assertNotContains('registration_number', $columns);

        $documentPath = (string) Config::get('uploads.paths.garage_documents', '');
        self::assertStringStartsWith('storage/private/', $documentPath);
    }

    public function testGarageAssetsAndDocumentsCannotBeReadByAnotherUser(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $ownerId = Database::insert(
            "INSERT INTO users (name,email,password_hash,status,created_at) VALUES ('Garage owner',?,'test','active',NOW())",
            ['garage-owner-' . $suffix . '@example.test']
        );
        $otherId = Database::insert(
            "INSERT INTO users (name,email,password_hash,status,created_at) VALUES ('Other owner',?,'test','active',NOW())",
            ['garage-other-' . $suffix . '@example.test']
        );

        try {
            $assetId = Database::insert(
                "INSERT INTO garage_assets (user_id,created_in_brand_id,asset_type,nickname,created_at) VALUES (?,1,'caravan','Tourer',NOW())",
                [$ownerId]
            );
            $documentId = Database::insert(
                "INSERT INTO garage_documents (garage_asset_id,document_type,label,stored_name,original_name,mime_type,file_size,created_at) "
                . "VALUES (?,'registration','Registration','opaque.pdf','registration.pdf','application/pdf',100,NOW())",
                [$assetId]
            );

            self::assertNotNull(GarageAsset::owned($assetId, $ownerId));
            self::assertNull(GarageAsset::owned($assetId, $otherId));
            self::assertNotNull(GarageAsset::ownedDocument($documentId, $ownerId));
            self::assertNull(GarageAsset::ownedDocument($documentId, $otherId));
        } finally {
            Database::query('DELETE FROM users WHERE id IN (?,?)', [$ownerId, $otherId]);
        }
    }

    public function testComplianceGrowthAndFreshnessSchemaIsInstalled(): void
    {
        foreach ([
            'regulatory_journeys', 'regulatory_alert_subscriptions', 'regulatory_alert_deliveries',
            'regulatory_provider_handoffs', 'provider_capability_credentials', 'advertising_campaign_daily_metrics',
        ] as $table) {
            self::assertTrue(Database::tableExists($table), $table . ' was not installed');
        }
        foreach (['objective','daily_budget_cents','total_budget_cents','billing_model','unit_price_cents'] as $column) {
            self::assertSame(1, (int) Database::scalar(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'advertising_campaigns\' AND column_name=?',
                [$column]
            ));
        }
        self::assertSame(2, (int) Database::scalar("SELECT COUNT(*) FROM permissions WHERE slug IN ('regulatory.manage','campaigns.manage')"));
        self::assertSame(1, (int) Database::scalar("SELECT COUNT(*) FROM scheduled_tasks WHERE task_key='regulatory_alerts'"));
    }

    public function testChangedOfficialSourceQueuesOnlyConsentedMatchingAlert(): void
    {
        $suffix = bin2hex(random_bytes(5));
        $email = 'regulatory-alert-' . $suffix . '@example.test';
        $userId = Database::insert("INSERT INTO users (name,email,password_hash,status,created_at) VALUES ('Alert owner',?,'test','active',NOW())", [$email]);
        $document = Database::selectOne(
            "SELECT d.* FROM regulatory_documents d INNER JOIN regulatory_document_brands db ON db.document_id=d.id AND db.brand_id=1 "
            . "WHERE d.jurisdiction_code='QLD' AND JSON_CONTAINS(d.vehicle_classes_json,JSON_QUOTE('trailer')) LIMIT 1"
        );
        self::assertNotNull($document);
        $documentId = (int) $document['id'];
        $subscriptionId = Database::insert(
            "INSERT INTO regulatory_alert_subscriptions (user_id,brand_id,jurisdiction_code,vehicle_class,document_kind,status,email_enabled,consented_at,consent_source,created_at) VALUES (?,1,'QLD','trailer',?,'active',1,NOW(),'integration',NOW())",
            [$userId, (string) $document['document_kind']]
        );
        try {
            Database::query("UPDATE regulatory_documents SET publication_status='review',change_detected_at=NOW() WHERE id=?", [$documentId]);
            $result = (new RegulatoryAlertService())->queueReviewedChanges();
            self::assertGreaterThanOrEqual(1, $result['queued']);
            self::assertSame(1, (int) Database::scalar('SELECT COUNT(*) FROM regulatory_alert_deliveries WHERE subscription_id=? AND document_id=? AND status=\'queued\'', [$subscriptionId, $documentId]));
            self::assertSame(1, (int) Database::scalar('SELECT COUNT(*) FROM email_queue WHERE recipient_email=? AND template_key=\'regulatory_source_change\'', [$email]));
            self::assertSame(0, count(\App\Models\RegulatoryDocument::publicLibrary(1, ['jurisdiction' => 'QLD', 'vehicle' => 'trailer', 'kind' => (string) $document['document_kind'], 'q' => (string) $document['title']])));
        } finally {
            Database::query('DELETE FROM email_queue WHERE recipient_email=?', [$email]);
            Database::query('DELETE FROM users WHERE id=?', [$userId]);
            Database::query('UPDATE regulatory_documents SET publication_status=?,change_detected_at=? WHERE id=?', [$document['publication_status'], $document['change_detected_at'], $documentId]);
            BrandContext::clear();
        }
    }

    public function testCampaignMetricsRemainCampaignScoped(): void
    {
        $providerId = Database::insert(
            "INSERT INTO providers (business_name,slug,email,status,created_at) VALUES ('Metric provider',?,'metric@example.test','active',NOW())",
            ['metric-provider-' . bin2hex(random_bytes(4))]
        );
        $campaignId = Database::insert(
            "INSERT INTO advertising_campaigns (brand_id,advertiser_provider_id,name,objective,status,headline,destination_url,daily_budget_cents,total_budget_cents,billing_model,unit_price_cents,created_at) VALUES (1,?,'Metric test','provider_profile','active','Relevant help','https://example.test',1000,5000,'cpc',250,NOW())",
            [$providerId]
        );
        try {
            CampaignMetrics::impressions([$campaignId, $campaignId]);
            CampaignMetrics::click($campaignId, 250);
            CampaignMetrics::conversion($campaignId);
            $metric = Database::selectOne('SELECT * FROM advertising_campaign_daily_metrics WHERE campaign_id=? AND metric_date=CURRENT_DATE', [$campaignId]);
            self::assertNotNull($metric);
            self::assertSame(1, (int) $metric['impressions']);
            self::assertSame(1, (int) $metric['clicks']);
            self::assertSame(1, (int) $metric['conversions']);
            self::assertSame(250, (int) $metric['spend_cents']);
        } finally {
            Database::query('DELETE FROM providers WHERE id=?', [$providerId]);
        }
    }

    public function testAgreedMembershipCatalogueIsInstalledWithoutActivatingBilling(): void
    {
        $plans = Database::select(
            "SELECT slug, public_name, monthly_price_cents, annual_price_cents FROM billing_plans "
            . "WHERE slug IN ('launch_access','free_listing','founding_verified','verified_provider','featured_provider') ORDER BY display_order"
        );

        self::assertSame(
            ['launch_access', 'free_listing', 'founding_verified', 'verified_provider', 'featured_provider'],
            array_column($plans, 'slug')
        );
        self::assertSame(1000, (int) $plans[2]['monthly_price_cents']);
        self::assertSame(15000, (int) $plans[3]['annual_price_cents']);
        self::assertSame(29000, (int) $plans[4]['annual_price_cents']);
        self::assertSame(50, (int) Database::scalar(
            "SELECT COUNT(*) FROM billing_plan_features f JOIN billing_plans p ON p.id=f.plan_id "
            . "WHERE p.slug IN ('launch_access','free_listing','founding_verified','verified_provider','featured_provider')"
        ));
        self::assertFalse((bool) Config::get('billing.enabled', false));
    }

    public function testPersistentRateLimitBlocksAndClears(): void
    {
        $subjects = ['email:integration-rate-limit@example.com', 'ip:192.0.2.10'];
        RateLimiter::clear('test.integration', $subjects);

        RateLimiter::hit('test.integration', $subjects, 2, 60, 60);
        self::assertFalse(RateLimiter::blocked('test.integration', $subjects));

        RateLimiter::hit('test.integration', $subjects, 2, 60, 60);
        self::assertTrue(RateLimiter::blocked('test.integration', $subjects));

        RateLimiter::clear('test.integration', $subjects);
        self::assertFalse(RateLimiter::blocked('test.integration', $subjects));
    }

    public function testRateLimitMiddlewareReturns429AfterQuota(): void
    {
        $subjects = ['platform|ip:192.0.2.11'];
        RateLimiter::clear('test.middleware', $subjects);
        $middleware = new RateLimitMiddleware('test.middleware', '1', '60', '60');
        $request = new Request([], [], [
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/test',
            'REMOTE_ADDR' => '192.0.2.11',
        ], []);

        $first = $middleware->handle($request, static fn (): Response => new Response('', 204));
        $second = $middleware->handle($request, static fn (): Response => new Response('', 204));

        self::assertSame(204, $first->status());
        self::assertSame(429, $second->status());
        self::assertSame('60', $second->headers()['Retry-After']);
        RateLimiter::clear('test.middleware', $subjects);
    }

    public function testEmailQueueClaimsAreLeasedAtomically(): void
    {
        $id = Database::insert(
            "INSERT INTO email_queue (brand_id, recipient_email, subject, html_body, status, created_at) "
            . "VALUES (1, 'lease-test@example.com', 'Lease test', '<p>test</p>', 'pending', NOW())"
        );
        $claim = new \ReflectionMethod(Mailer::class, 'claimBatch');

        try {
            /** @var array<int,array<string,mixed>> $first */
            $first = $claim->invoke(null, 100, 3);
            $row = null;
            foreach ($first as $candidate) {
                if ((int) $candidate['id'] === $id) {
                    $row = $candidate;
                    break;
                }
            }
            self::assertNotNull($row);
            self::assertSame('processing', $row['status']);
            self::assertSame(1, (int) $row['attempts']);
            self::assertSame(32, strlen((string) $row['lease_token']));

            /** @var array<int,array<string,mixed>> $second */
            $second = $claim->invoke(null, 100, 3);
            self::assertNotContains($id, array_map(
                static fn (array $candidate): int => (int) $candidate['id'],
                $second
            ));
        } finally {
            Database::query('DELETE FROM email_queue WHERE id = ?', [$id]);
        }
    }

    public function testEmailQueueCanClaimOneExactDashboardTestMessage(): void
    {
        $firstId = Database::insert(
            "INSERT INTO email_queue (brand_id, recipient_email, subject, html_body, status, created_at) "
            . "VALUES (1, 'older-test@example.com', 'Older', '<p>older</p>', 'pending', NOW())"
        );
        $targetId = Database::insert(
            "INSERT INTO email_queue (brand_id, recipient_email, subject, html_body, status, created_at) "
            . "VALUES (1, 'exact-test@example.com', 'Exact', '<p>exact</p>', 'pending', NOW())"
        );
        $claim = new \ReflectionMethod(Mailer::class, 'claimBatch');

        try {
            /** @var array<int,array<string,mixed>> $rows */
            $rows = $claim->invoke(null, 1, 3, $targetId);
            self::assertCount(1, $rows);
            self::assertSame($targetId, (int) $rows[0]['id']);
            self::assertSame('pending', (string) Database::scalar('SELECT status FROM email_queue WHERE id=?', [$firstId]));
        } finally {
            Database::query('DELETE FROM email_queue WHERE id IN (?,?)', [$firstId, $targetId]);
        }
    }

    public function testVanAssistProviderCampaignIsPreparedButNotQueued(): void
    {
        \App\Services\ProviderCampaignDrafts::prepareForBrand(1);
        $campaign = Database::selectOne(
            "SELECT status,delivery_stage,recipient_count,body FROM notifications WHERE brand_id=1 "
            . "AND title='Please check how travellers find your business on VanAssist'"
        );

        self::assertNotNull($campaign);
        self::assertSame('draft', $campaign['status']);
        self::assertSame('draft', $campaign['delivery_stage']);
        self::assertSame(0, (int) $campaign['recipient_count']);
        self::assertStringContainsString('https://vanassist.com.au/for-providers', (string) $campaign['body']);

        $categoryCampaign = Database::selectOne(
            "SELECT status,delivery_stage,recipient_count,audience_type,body FROM notifications WHERE brand_id=1 "
            . "AND audience_type='provider_category' ORDER BY id LIMIT 1"
        );
        self::assertNotNull($categoryCampaign);
        self::assertSame('draft', $categoryCampaign['status']);
        self::assertSame('draft', $categoryCampaign['delivery_stage']);
        self::assertSame(0, (int) $categoryCampaign['recipient_count']);
        self::assertStringContainsString('/runtime-assets/img/provider-', (string) $categoryCampaign['body']);
    }

    public function testEmailQueuePersistsCurrentBrandContext(): void
    {
        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        BrandContext::set($registry->get('towsmart'));
        $queueId = null;

        try {
            EmailQueue::queueRaw(
                'brand-test@example.com',
                'Brand test',
                'Brand context',
                '<p>test</p>'
            );
            $row = Database::selectOne(
                "SELECT id, brand_id FROM email_queue WHERE recipient_email = 'brand-test@example.com' "
                . 'ORDER BY id DESC LIMIT 1'
            );
            self::assertNotNull($row);
            $queueId = (int) $row['id'];
            self::assertSame(2, (int) $row['brand_id']);
        } finally {
            if ($queueId !== null) {
                Database::query('DELETE FROM email_queue WHERE id = ?', [$queueId]);
            }
            BrandContext::clear();
        }
    }

    public function testQueuedBrandsUseTheirOwnSenderDomains(): void
    {
        $towSmart = Mailer::config(2);
        $trailerWise = Mailer::config(3);

        self::assertSame('support@towsmart.com.au', $towSmart['from_address']);
        self::assertSame('TowSmart', $towSmart['from_name']);
        self::assertSame('support@trailerwise.com.au', $trailerWise['from_address']);
        self::assertSame('TrailerWise', $trailerWise['from_name']);
        self::assertStringNotContainsString('vanassist.com.au', (string) $towSmart['from_address']);
        self::assertStringNotContainsString('vanassist.com.au', (string) $trailerWise['from_address']);
    }

    public function testSharedTemplatesAndDedicatedMailboxProbesAreBrandSafe(): void
    {
        $sharedKeys = [
            'email_verification', 'password_reset', 'provider_invitation',
            'provider_application_received', 'provider_approved', 'provider_rejected',
        ];
        $placeholders = implode(',', array_fill(0, count($sharedKeys), '?'));
        $templates = Database::select(
            "SELECT template_key,subject,html_body,text_body FROM email_templates WHERE template_key IN ({$placeholders})",
            $sharedKeys
        );

        self::assertCount(count($sharedKeys), $templates);
        foreach ($templates as $template) {
            $copy = implode("\n", [
                (string) $template['subject'],
                (string) $template['html_body'],
                (string) $template['text_body'],
            ]);
            self::assertStringContainsString('{{brand_name}}', $copy);
            self::assertStringNotContainsString('VanAssist', $copy);
        }

        self::assertSame(3, (int) Database::scalar(
            "SELECT COUNT(*) FROM email_queue WHERE template_key IN "
            . "('vanassist_dedicated_mailbox_probe_20260728','towsmart_dedicated_mailbox_probe_20260728','trailerwise_dedicated_mailbox_probe_20260728') "
            . "AND status IN ('pending','processing','sent')"
        ));
    }

    public function testFacebookPublishingAuditColumnsAreInstalled(): void
    {
        foreach (['facebook_post_id','facebook_publish_error','facebook_published_at','facebook_published_by'] as $column) {
            self::assertSame(1, (int) Database::scalar(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'social_media_assets\' AND column_name=?',
                [$column]
            ));
        }
    }

    public function testTemplateQueueInjectsCurrentBrandIdentity(): void
    {
        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        BrandContext::set($registry->get('towsmart'));
        $templateKey = 'integration_brand_placeholders';
        $queueId = null;
        Database::query(
            "INSERT INTO email_templates (template_key, name, subject, html_body, text_body, is_enabled, created_at) VALUES (?, 'Integration', '{{brand_name}} notice', '<p>{{brand_domain}} {{support_email}}</p>', '{{site_url}}', 1, NOW())",
            [$templateKey]
        );

        try {
            self::assertTrue(EmailQueue::queueTemplate($templateKey, 'brand-template@example.com'));
            $row = Database::selectOne('SELECT * FROM email_queue WHERE template_key = ? ORDER BY id DESC LIMIT 1', [$templateKey]);
            self::assertNotNull($row);
            $queueId = (int) $row['id'];
            self::assertSame(2, (int) $row['brand_id']);
            self::assertSame('TowSmart notice', $row['subject']);
            self::assertStringContainsString($registry->get('towsmart')->primaryDomain(), (string) $row['html_body']);
            self::assertStringNotContainsString('vanassist.com.au', (string) $row['html_body']);
        } finally {
            if ($queueId !== null) { Database::query('DELETE FROM email_queue WHERE id = ?', [$queueId]); }
            Database::query('DELETE FROM email_templates WHERE template_key = ?', [$templateKey]);
            BrandContext::clear();
        }
    }

    public function testMarketingSuppressionDoesNotBlockTransactionalEmail(): void
    {
        $email = 'suppression-integration@example.com';
        $transactionalId = null;
        try {
            self::assertTrue(Database::tableExists('email_suppressions'));
            foreach (['marketing_opt_in','marketing_consented_at','marketing_consent_source','marketing_consent_evidence'] as $column) {
                self::assertSame(1, (int) Database::scalar(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'providers\' AND column_name=?',
                    [$column]
                ));
            }
            foreach (['marketing_consented_at','marketing_consent_basis','marketing_consent_evidence'] as $column) {
                self::assertSame(1, (int) Database::scalar(
                    'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=\'provider_prospects\' AND column_name=?',
                    [$column]
                ));
            }
            EmailSuppression::suppressMarketing($email, 'integration');
            self::assertTrue(EmailSuppression::isSuppressed($email, 'marketing'));
            self::assertFalse(EmailSuppression::isSuppressed($email, 'transactional'));
            self::assertFalse(EmailQueue::queueRaw($email, null, 'Marketing', '<p>Marketing</p>', 'Marketing', null, null, 'marketing'));
            self::assertTrue(EmailQueue::queueRaw($email, null, 'Account', '<p>Account</p>', 'Account'));

            $transactionalId = (int) Database::scalar(
                "SELECT id FROM email_queue WHERE recipient_email=? AND subject='Account' ORDER BY id DESC LIMIT 1",
                [$email]
            );
            self::assertGreaterThan(0, $transactionalId);
            self::assertSame(0, (int) Database::scalar(
                "SELECT COUNT(*) FROM email_queue WHERE recipient_email=? AND subject='Marketing'",
                [$email]
            ));
            EmailSuppression::suppressAll($email, 'hard_bounce', 'integration');
            self::assertTrue(EmailSuppression::isSuppressed($email, 'transactional'));
            self::assertFalse(EmailQueue::queueRaw($email, null, 'Blocked account', '<p>Blocked</p>'));
        } finally {
            if ($transactionalId !== null) { Database::query('DELETE FROM email_queue WHERE id=?', [$transactionalId]); }
            Database::query('DELETE FROM email_suppressions WHERE email=?', [$email]);
        }
    }

    public function testNationalTownCoordinatePackActivatesWithNormalisedNameVariants(): void
    {
        $result = TownCoordinateActivation::afterMigrations();

        self::assertArrayHasKey('updated', $result);
        self::assertGreaterThan(1000, (int) $result['updated']);
        self::assertSame('authoritative', Database::scalar(
            "SELECT coordinate_confidence FROM towns t JOIN states s ON s.id=t.state_id WHERE s.abbreviation='ACT' AND t.slug='o-connor'"
        ));
    }

    public function testStagedCampaignSchemaIsInstalled(): void
    {
        self::assertTrue(Database::tableExists('notification_test_deliveries'));
        foreach (['delivery_stage','last_batch_at','stage_reviewed_at','stage_reviewed_by','auto_continue_enabled','auto_continue_enabled_at','auto_continue_enabled_by','auto_continue_next_at','auto_continue_last_run_at','auto_continue_last_error'] as $column) {
            self::assertSame(1, (int) Database::scalar(
                "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='notifications' AND column_name=?",
                [$column]
            ));
        }
        self::assertSame(1, (int) Database::scalar(
            "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name='email_queue' AND column_name='notification_id'"
        ));
        self::assertSame(1, (int) Database::scalar(
            "SELECT COUNT(DISTINCT index_name) FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='notification_recipients' AND index_name='uq_notification_recipient_email'"
        ));
    }

    public function testInvoiceExportsAreWiredForExternalAccounting(): void
    {
        $invoiceId = Database::insert(
            "INSERT INTO invoices (invoice_number,invoice_date,due_date,status,currency,gst_inclusive,subtotal_cents,gst_cents,total_cents,amount_paid_cents,business_name,billing_address,created_at) VALUES ('TEST-EXPORT-001','2026-07-28','2026-08-11','open','AUD',1,10000,1000,11000,0,'Example Workshop','1 Test Street, Brisbane',NOW())"
        );
        try {
            Database::query(
                "INSERT INTO invoice_items (invoice_id,description,quantity,unit_amount_cents,amount_cents,gst_cents,created_at) VALUES (?,'Platform subscription',1,11000,11000,1000,NOW())",
                [$invoiceId]
            );
            $xero = InvoiceExportService::download('xero');
            self::assertSame(200, $xero->status());
            self::assertStringContainsString('ContactName,EmailAddress', $xero->content());
            self::assertStringContainsString('TEST-EXPORT-001', $xero->content());
            self::assertStringContainsString('28/07/2026', $xero->content());

            $myob = InvoiceExportService::download('myob');
            self::assertSame(200, $myob->status());
            self::assertStringContainsString('Co./Last Name', $myob->content());
            self::assertStringContainsString('Platform subscription', $myob->content());
        } finally {
            Database::query('DELETE FROM invoice_items WHERE invoice_id=?', [$invoiceId]);
            Database::query('DELETE FROM invoices WHERE id=?', [$invoiceId]);
        }
    }
}
