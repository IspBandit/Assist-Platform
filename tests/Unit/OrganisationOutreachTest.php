<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NotificationService;
use App\Services\OrganisationCampaignCopy;
use App\Services\OrganisationOutreach;
use PHPUnit\Framework\TestCase;

final class OrganisationOutreachTest extends TestCase
{
    public function testRegisterHasExplicitTargetTypesAndFailClosedStatuses(): void
    {
        self::assertArrayHasKey('club', OrganisationOutreach::TYPES);
        self::assertArrayHasKey('manufacturer', OrganisationOutreach::TYPES);
        self::assertArrayHasKey('rental_fleet', OrganisationOutreach::TYPES);
        self::assertArrayHasKey('publication', OrganisationOutreach::TYPES);
        self::assertArrayHasKey('touring_association', OrganisationOutreach::TYPES);
        self::assertSame('Research — not reviewed', OrganisationOutreach::STATUSES['research']);
        self::assertArrayHasKey('do_not_contact', OrganisationOutreach::STATUSES);
        self::assertArrayHasKey('shared', OrganisationOutreach::OUTCOMES);
        self::assertArrayHasKey('opted_out', OrganisationOutreach::OUTCOMES);
    }

    public function testEligibilityRequiresCurrentSourceHumanReviewAndNoSafetyFlags(): void
    {
        $service = $this->source('app/Services/OrganisationOutreach.php');
        self::assertStringContainsString("review_status='eligible'", $service);
        self::assertStringContainsString('reviewed_by IS NOT NULL', $service);
        self::assertStringContainsString('source_checked_at>=DATE_SUB(CURDATE(),INTERVAL 180 DAY)', $service);
        self::assertStringContainsString('no_unsolicited_warning=0', $service);
        self::assertStringContainsString('personal_or_ambiguous=0', $service);
        self::assertStringContainsString("'inferred_role_relevant'", $service);
        self::assertStringContainsString("isSuppressed(\$email, 'marketing')", $service);
    }

    public function testCampaignCopyIsSegmentedAndDoesNotRequestMemberOrCustomerLists(): void
    {
        $styles = OrganisationCampaignCopy::styles();
        foreach (['club_member_resource','industry_data_collaboration','fleet_dealer_owner_support','editorial_story','tourism_visitor_resource'] as $key) {
            self::assertArrayHasKey($key, $styles);
            self::assertStringContainsString('vanassist.com.au', $styles[$key]['body']);
        }
        $copy = strtolower(implode(' ', array_column($styles, 'body')));
        self::assertStringContainsString('not asking for your member list', $copy);
        self::assertStringContainsString('not a request for customer data', $copy);
        self::assertStringContainsString('will not imply endorsement', $copy);
        self::assertStringNotContainsString('guaranteed', $copy);
    }

    public function testHubRoutesAndMobileLayoutArePresent(): void
    {
        $routes = $this->source('routes/admin.php');
        $view = $this->source('app/Views/admin/outreach-hub/index.php');
        $css = $this->source('public/assets/css/app.css');
        $controller = $this->source('app/Controllers/Admin/OutreachHubController.php');
        self::assertStringContainsString("/outreach-hub", $routes);
        self::assertStringContainsString('/outreach-hub/outcome', $routes);
        self::assertStringContainsString('Published does not mean permission', $view);
        self::assertStringContainsString('Import for review', $view);
        self::assertStringContainsString('Record outcome', $view);
        self::assertStringContainsString('Recent outreach history', $view);
        self::assertStringContainsString('Sent by platform', $view);
        self::assertStringContainsString('Free Growth Hub', $view);
        self::assertStringContainsString('Tracked free-share kit', $view);
        self::assertStringContainsString('Email clubs and relevant organisations', $view);
        self::assertStringContainsString('Create email campaign', $view);
        self::assertStringContainsString("'organisation_type' => \$segment['type']", $controller);
        self::assertStringContainsString("'copy_style' => \$segment['style']", $controller);
        self::assertStringContainsString('data-copy-target', $view);
        self::assertStringContainsString('Google Search Console', $view);
        self::assertStringContainsString('Free growth hub', $this->source('app/Views/layouts/admin.php'));
        self::assertStringContainsString('Open free growth hub', $this->source('app/Views/admin/dashboard.php'));
        self::assertStringContainsString("'utm_source'", $controller);
        self::assertStringContainsString("'facebook_group'", $controller);
        $notifications = $this->source('app/Controllers/Admin/NotificationsController.php');
        self::assertStringContainsString("OrganisationCampaignCopy::styles()[\$copyStyle]", $notifications);
        self::assertStringContainsString("\$values['title'] = \$style['subject']", $notifications);
        $campaignList = $this->source('app/Views/admin/notifications/index.php');
        self::assertStringContainsString('Send preview to me', $campaignList);
        self::assertStringContainsString('Start sending (max 25)', $campaignList);
        self::assertStringContainsString('Send next batch (50/day)', $campaignList);
        self::assertStringContainsString('max-height:68vh', $css);
    }

    public function testOrganisationDeliveryRetainsEvidenceAndUsesStagedLimits(): void
    {
        self::assertSame(25, NotificationService::STAGE_LIMITS['pilot']);
        $migration = $this->source('database/migrations/084_organisation_outreach_register.sql');
        $delivery = $this->source('app/Services/NotificationService.php');
        self::assertStringContainsString("'organisation_outreach'", $migration);
        self::assertStringContainsString('organisation_contact_id', $migration);
        self::assertStringContainsString('role_relevant_publication', $delivery);
        self::assertStringContainsString('one-time introduction', $delivery);
        self::assertStringContainsString('CREATE TABLE organisation_outreach_events', $migration);
        self::assertStringContainsString("OrganisationOutreach::event((int) \$recipient['organisation_contact_id'], 'queued'", $delivery);
        self::assertStringNotContainsString('SET last_contacted_at=NOW()', $delivery);

        $mailer = $this->source('app/Services/Mailer.php');
        self::assertStringContainsString("'sent','Accepted by the configured outbound mail transport.'", $mailer);
        self::assertStringContainsString("'suppressed','Suppressed before transport.'", $mailer);
    }

    public function testReleaseIncludesAValidIdempotentResearchSeed(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/outreach/vanassist-organisations.csv';
        self::assertFileExists($path);
        $handle = fopen($path, 'r');
        self::assertNotFalse($handle);
        $header = fgetcsv($handle, null, ',', '"', '');
        self::assertIsArray($header);
        $emails = [];
        while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
            self::assertCount(count($header), $row);
            $record = array_combine($header, $row);
            self::assertIsArray($record);
            self::assertTrue(filter_var($record['email'], FILTER_VALIDATE_EMAIL) !== false);
            self::assertTrue(filter_var($record['source_url'], FILTER_VALIDATE_URL) !== false);
            $emails[] = strtolower($record['email']);
        }
        fclose($handle);
        self::assertCount(63, $emails);
        self::assertCount(63, array_unique($emails));

        $runner = $this->source('scripts/migrate.php');
        self::assertStringContainsString('OrganisationOutreachImporter::afterMigrations()', $runner);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);
        return $contents;
    }
}
