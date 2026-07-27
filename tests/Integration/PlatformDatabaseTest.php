<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Database;
use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\RateLimit as RateLimitMiddleware;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\EmailQueue;
use App\Services\EmailSuppression;
use App\Services\Mailer;
use App\Services\PlatformBackfill;
use App\Services\RateLimiter;
use App\Services\RegulatoryAlertService;
use App\Services\CampaignMetrics;
use App\Models\GarageAsset;
use App\Models\Provider;
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

        $fuelCategoryId = (int) Database::scalar(
            "SELECT id FROM service_categories WHERE slug='fuel-and-travel-stops'"
        );
        $nearGladstone = Provider::forCategoryNear($fuelCategoryId, -23.842, 151.255, 150);
        self::assertNotEmpty($nearGladstone);
        self::assertLessThanOrEqual(150.0, (float) $nearGladstone[0]['distance_km']);
    }

    public function testPlatformBrandsAndBackfillIntegrity(): void
    {
        $brands = Database::select('SELECT id, brand_key, status FROM brands ORDER BY id');
        self::assertSame(['vanassist', 'towsmart', 'trailerwise', 'localtorque'], array_column($brands, 'brand_key'));
        self::assertSame('active', $brands[0]['status']);

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

    public function testStagedCampaignSchemaIsInstalled(): void
    {
        self::assertTrue(Database::tableExists('notification_test_deliveries'));
        foreach (['delivery_stage','last_batch_at','stage_reviewed_at','stage_reviewed_by'] as $column) {
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
}
