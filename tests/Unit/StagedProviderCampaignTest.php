<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NotificationService;
use App\Services\CampaignRecipientManager;
use App\Services\DirectoryAccuracyNotice;
use App\Services\ProviderCampaignCopy;
use App\Services\ProviderCampaignDrafts;
use App\Services\ProviderPackActivation;
use PHPUnit\Framework\TestCase;

final class StagedProviderCampaignTest extends TestCase
{
    public function testCampaignLimitsAreConservativeAndExplicit(): void
    {
        self::assertSame(['pilot' => 25, 'daily_50' => 50, 'daily_100' => 100], NotificationService::STAGE_LIMITS);

        $service = $this->source('app/Services/NotificationService.php');
        self::assertStringContainsString("'test' => ['pilot']", $service);
        self::assertStringContainsString("'pilot' => ['daily_50']", $service);
        self::assertStringContainsString("DATE_SUB(NOW(), INTERVAL 24 HOUR)", $service);
    }

    public function testProviderAudienceFailsClosedWithoutConsentEvidence(): void
    {
        $audience = $this->source('app/Services/BroadcastAudience.php');
        self::assertStringContainsString('p.marketing_consented_at IS NOT NULL', $audience);
        self::assertStringContainsString('p.marketing_consent_source IN', $audience);
        self::assertStringContainsString("marketing_consent_evidence),'') IS NOT NULL", $audience);
        self::assertStringNotContainsString('treated as operational business contacts', $audience);
        self::assertStringContainsString("case 'provider_category'", $audience);
        self::assertStringContainsString('providerEmailSummary', $audience);
    }

    public function testServiceFamilyCopyIsRelevantHumanAndNotOverstated(): void
    {
        $styles = ProviderCampaignCopy::styles();
        foreach (['workshop','electrical','tyres','rv','trailer','fuel','compliance','stays'] as $key) {
            self::assertArrayHasKey($key, $styles);
            self::assertNotSame('', trim($styles[$key]['subject']));
            self::assertStringContainsString('<p>', $styles[$key]['body']);
        }
        self::assertStringContainsString('Fuel gauges', $styles['fuel']['body']);
        self::assertStringContainsString('not a towing calculation', $styles['trailer']['body']);
        self::assertStringNotContainsString('guaranteed', strtolower(implode(' ', array_column($styles, 'body'))));
        self::assertStringContainsString('provider-workshop.webp', $styles['workshop']['body']);
        self::assertStringContainsString('not promising a miraculous queue of leads', $styles['workshop']['body']);
    }

    public function testEveryServiceCategoryMapsToOneCampaignFamily(): void
    {
        self::assertSame('fuel', ProviderCampaignCopy::familyForCategory('ev-charging'));
        self::assertSame('trailer', ProviderCampaignCopy::familyForCategory('weighbridges-and-mobile-weighing'));
        self::assertSame('stays', ProviderCampaignCopy::familyForCategory('caravan-parks-and-campgrounds'));
        self::assertSame('compliance', ProviderCampaignCopy::familyForCategory('roadworthy-inspections'));
        self::assertSame('workshop', ProviderCampaignCopy::familyForCategory('diesel-mechanics'));

        $draft = ProviderCampaignCopy::forCategory('EV charging', 'ev-charging');
        self::assertSame('A quick accuracy check for EV charging on VanAssist', $draft['subject']);
        self::assertStringContainsString('<strong>EV charging</strong>', $draft['body']);
        self::assertStringContainsString('provider-fuel.webp', $draft['body']);
    }

    public function testCampaignDraftPreparationIsVanAssistScoped(): void
    {
        self::assertSame(0, ProviderCampaignDrafts::prepareForBrand(2));
        self::assertSame('Please check the business information VanAssist currently displays', DirectoryAccuracyNotice::subject('VanAssist'));
        self::assertStringContainsString('VanAssist', DirectoryAccuracyNotice::previewBody('VanAssist'));
        $source = $this->source('app/Services/ProviderCampaignDrafts.php');
        self::assertStringContainsString("'provider_category'", $source);
        self::assertStringContainsString("'directory_accuracy'", $source);
        self::assertStringContainsString('DirectoryAccuracyNotice::previewBody($brandName)', $source);
        self::assertStringContainsString("'draft','draft'", $source);
        self::assertStringContainsString('NOT EXISTS', $source);
        self::assertStringContainsString('brand_provider_categories', $source);
        self::assertStringContainsString('provider_brand_category_id', $source);
        self::assertStringNotContainsString('INNER JOIN provider_services ps ON ps.category_id=sc.id', $source);
    }

    public function testProviderImportsAutomaticallyPrepareDraftsWithoutQueuingDelivery(): void
    {
        $runner = $this->source('app/Services/ProviderImportRunner.php');
        $seeder = $this->source('app/Services/Seeder.php');

        self::assertStringContainsString('? ProviderCampaignDrafts::prepareForBrand(1)', $runner);
        self::assertStringContainsString("'campaign_drafts_created'", $runner);
        self::assertStringContainsString('ProviderCampaignDrafts::prepareForBrand(1);', $seeder);
        self::assertStringNotContainsString('NotificationService::queueStage', $runner);
        self::assertStringNotContainsString('NotificationService::queueStage', $seeder);

        $localTorque = $this->source('app/Services/LocalTorquePackSeeder.php');
        self::assertStringContainsString('ImportProvenance::sourceUrl($record)', $localTorque);
    }

    public function testPaidStayDiscoveryIsReviewOnlyAndAuthorityGated(): void
    {
        $migration = $this->source('database/migrations/081_caravan_stay_import_review.sql');
        $service = $this->source('app/Services/CaravanStayImportService.php');
        $view = $this->source('app/Views/admin/parks/import.php');

        self::assertStringContainsString('caravan_stay_import_candidates', $migration);
        self::assertStringContainsString("public_page_enabled,status", $service);
        self::assertStringContainsString("0,'draft'", $service);
        self::assertStringContainsString('AUTHORITY_TYPES', $service);
        self::assertStringContainsString("str_ends_with(\$host,'.gov.au')", str_replace(' ', '', $service));
        self::assertStringContainsString('Nothing on this page publishes automatically', $view);
        self::assertStringContainsString('A current Australian government or council source is required', $view);
    }

    public function testVanAssistSitemapUsesBrandListingSlugsAndActiveVisibility(): void
    {
        $source = $this->source('app/Controllers/Site/SitemapController.php');
        self::assertStringContainsString('FROM provider_brand_listings pbl', $source);
        self::assertStringContainsString("pbl.brand_id=? AND pbl.status='active' AND pbl.search_visible=1", $source);
        self::assertStringNotContainsString("SELECT slug, updated_at FROM providers WHERE status = 'active'", $source);
    }

    public function testComposeScreenHasNoBulkSendNowControl(): void
    {
        $view = $this->source('app/Views/admin/notifications/compose.php');
        self::assertStringNotContainsString('Send now', $view);
        self::assertStringContainsString('Save staged campaign', $view);
        self::assertStringContainsString('Apply starter', $view);
        self::assertStringContainsString('Safety boundary', $view);
        self::assertStringContainsString('factual listing notice', strtolower($view));
    }

    public function testRecipientReviewShowsAllCandidatesWithoutBypassingConsent(): void
    {
        self::assertCount(4, CampaignRecipientManager::CONSENT_BASES);
        $service = $this->source('app/Services/CampaignRecipientManager.php');
        $view = $this->source('app/Views/admin/notifications/show.php');
        $routes = $this->source('routes/admin.php');
        $delivery = $this->source('app/Services/NotificationService.php');
        self::assertStringContainsString("p.status='active'", $service);
        self::assertStringContainsString('marketing_consented_at', $service);
        self::assertStringContainsString('assertNotSuppressed', $service);
        self::assertStringContainsString('notification_provider_exclusions', $service);
        self::assertStringContainsString('$statusByEmail', $service);
        self::assertStringContainsString('FILTER_VALIDATE_EMAIL', $service);
        self::assertStringContainsString("SELECT LOWER(email) AS email FROM email_suppressions WHERE scope IN ('marketing','all')", $service);
        self::assertStringContainsString('$statusByEmail', $service);
        self::assertStringContainsString('Record consent and add', $view);
        self::assertStringContainsString('Remove from campaign', $view);
        self::assertStringContainsString('/notifications/recipient-include', $routes);
        self::assertStringContainsString('CampaignRecipientManager::eligibleRecipients', $delivery);

        $mailer = $this->source('app/Services/Mailer.php');
        $suppression = $this->source('app/Services/EmailSuppression.php');
        self::assertStringContainsString('EmailSuppression::isSuppressed', $mailer);
        self::assertStringContainsString('markSuppressed($row)', $mailer);
        self::assertStringContainsString('cancelPending($email', $suppression);
    }

    public function testCampaignIndexSeparatesDeliveryCountsFromBoundedLiveAudienceSummaries(): void
    {
        $controller = $this->source('app/Controllers/Admin/NotificationsController.php');
        $view = $this->source('app/Views/admin/notifications/index.php');

        self::assertStringContainsString('LIMIT 250', $controller);
        self::assertStringContainsString('brand_provider_categories bpc', $controller);
        self::assertStringContainsString('CampaignRecipientManager::summary($notification)', $controller);
        self::assertStringContainsString('Queued / sent', $view);
        self::assertStringContainsString('eligible now', $view);
        self::assertStringContainsString('with email', $view);
        self::assertStringContainsString('held', $view);
        self::assertStringContainsString('suppressed', $view);
        self::assertStringContainsString('not inserted into delivery records', $view);
        self::assertStringContainsString('How to send a campaign', $view);
        self::assertStringContainsString('Send preview to me', $view);
        self::assertStringContainsString('Start sending (max 25)', $view);
        self::assertStringContainsString('Review details', $view);
        self::assertStringContainsString('Email campaigns', $this->source('app/Views/layouts/admin.php'));
        self::assertStringContainsString('Open email campaigns', $this->source('app/Views/admin/dashboard.php'));
        self::assertStringContainsString('Email preview to me', $this->source('app/Views/admin/notifications/show.php'));
        self::assertStringContainsString('id="delivery-controls"', $this->source('app/Views/admin/notifications/show.php'));
    }

    public function testCampaignRecipientsUseCanonicalBrandAssignmentsAndRejectInvalidEmail(): void
    {
        $service=$this->source('app/Services/CampaignRecipientManager.php');
        self::assertStringContainsString('provider_brand_category_assignments pbca',$service);
        self::assertStringContainsString('provider_brand_category_id',$service);
        self::assertStringContainsString('FILTER_VALIDATE_EMAIL',$service);
        self::assertStringContainsString('eligibleMarketingRecipients',$service);
    }

    public function testAutomaticContinuationIsOffByDefaultAndFactualOnlyAfterReviewedDaily100(): void
    {
        $migration = $this->source('database/migrations/082_directory_campaign_auto_continuation.sql');
        $controller = $this->source('app/Controllers/Admin/NotificationsController.php');
        $delivery = $this->source('app/Services/NotificationService.php');
        $view = $this->source('app/Views/admin/notifications/show.php');
        $cron = $this->source('app/Services/CronRunner.php');
        $routes = $this->source('routes/admin.php');

        self::assertStringContainsString('auto_continue_enabled TINYINT(1) NOT NULL DEFAULT 0', $migration);
        self::assertStringContainsString("!== 'directory_accuracy'", $controller);
        self::assertStringContainsString("!== 'daily_100'", $controller);
        self::assertStringContainsString('stage_reviewed_at', $controller);
        self::assertStringContainsString('stage_reviewed_by', $controller);
        self::assertStringContainsString("campaign_type='directory_accuracy'", $delivery);
        self::assertStringContainsString("delivery_stage='daily_100'", $delivery);
        self::assertStringContainsString("auto_continue_enabled=0", $delivery);
        self::assertStringContainsString("queueStage(\$id, 'daily_100', null)", $delivery);
        self::assertStringContainsString('continueDirectoryCampaigns()', $cron);
        self::assertStringContainsString('/notifications/auto-continue', $routes);
        self::assertStringContainsString('Switch off automatic continuation', $view);
        self::assertStringContainsString('never applies to marketing', $view);
    }

    public function testFactualDirectoryNoticesAreLockedAndSeparatedFromMarketing(): void
    {
        $migration = $this->source('database/migrations/079_outreach_campaign_boundaries.sql');
        $controller = $this->source('app/Controllers/Admin/NotificationsController.php');
        $delivery = $this->source('app/Services/NotificationService.php');
        $notice = $this->source('app/Services/DirectoryAccuracyNotice.php');

        self::assertStringContainsString("'provider_marketing','directory_accuracy'", $migration);
        self::assertStringContainsString('DirectoryAccuracyNotice::subject()', $controller);
        self::assertStringContainsString('DirectoryAccuracyNotice::assertFixed', $delivery);
        self::assertStringContainsString("'directory_accuracy'", $delivery);
        self::assertStringContainsString('does not subscribe this address to marketing', $notice);
        self::assertStringNotContainsString('claim your listing', strtolower($notice));
        self::assertStringNotContainsString('pricing offer', strtolower($notice));
    }

    public function testCampaignHeadersAreLightweightRetinaWebpAssets(): void
    {
        $directory = dirname(__DIR__, 2) . '/public/assets/img';
        foreach (['workshop','electrical','tyres','rv','trailer','fuel','compliance','stays'] as $family) {
            $path = $directory . '/provider-' . $family . '.webp';
            self::assertFileExists($path);
            self::assertLessThan(120_000, filesize($path));
            self::assertSame([1200, 400], array_slice((array) getimagesize($path), 0, 2));
        }
    }

    public function testProviderPackActivationOnlyRunsForSeededStaleProductionData(): void
    {
        self::assertFalse(ProviderPackActivation::shouldRun(999, 'new', '', '0'));
        self::assertFalse(ProviderPackActivation::shouldRun(15000, '', '', '0'));
        self::assertFalse(ProviderPackActivation::shouldRun(15000, 'same', 'same', 'done'));
        self::assertTrue(ProviderPackActivation::shouldRun(15000, 'new', 'old', 'done'));
        self::assertTrue(ProviderPackActivation::shouldRun(15000, 'same', 'same', '500'));
    }

    public function testCustomProviderGraphicServiceIsNotOfferedInTheProduct(): void
    {
        $providerRoutes = $this->source('routes/provider.php');
        $adminRoutes = $this->source('routes/admin.php');
        $providerDashboard = $this->source('app/Views/provider/dashboard.php');
        $claimView = $this->source('app/Views/provider/claim-accept.php');

        self::assertStringNotContainsString('/promotion', $providerRoutes);
        self::assertStringNotContainsString('/promotions', $adminRoutes);
        self::assertStringNotContainsString('free ad graphic', strtolower($providerDashboard));
        self::assertStringNotContainsString('free ad graphic', strtolower($claimView));
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);
        return $contents;
    }
}
