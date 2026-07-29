<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NotificationService;
use App\Services\CampaignRecipientManager;
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
        $source = $this->source('app/Services/ProviderCampaignDrafts.php');
        self::assertStringContainsString("'provider_category'", $source);
        self::assertStringContainsString("'draft','draft'", $source);
        self::assertStringContainsString('NOT EXISTS', $source);
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
        self::assertStringContainsString('Record consent and add', $view);
        self::assertStringContainsString('Remove from campaign', $view);
        self::assertStringContainsString('/notifications/recipient-include', $routes);
        self::assertStringContainsString('CampaignRecipientManager::eligibleRecipients', $delivery);
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
