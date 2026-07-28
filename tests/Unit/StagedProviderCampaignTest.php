<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NotificationService;
use App\Services\ProviderCampaignCopy;
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
    }

    public function testComposeScreenHasNoBulkSendNowControl(): void
    {
        $view = $this->source('app/Views/admin/notifications/compose.php');
        self::assertStringNotContainsString('Send now', $view);
        self::assertStringContainsString('Save staged campaign', $view);
        self::assertStringContainsString('Apply starter', $view);
    }

    public function testProviderPackActivationOnlyRunsForSeededStaleProductionData(): void
    {
        self::assertFalse(ProviderPackActivation::shouldRun(999, 'new', '', '0'));
        self::assertFalse(ProviderPackActivation::shouldRun(15000, '', '', '0'));
        self::assertFalse(ProviderPackActivation::shouldRun(15000, 'same', 'same', 'done'));
        self::assertTrue(ProviderPackActivation::shouldRun(15000, 'new', 'old', 'done'));
        self::assertTrue(ProviderPackActivation::shouldRun(15000, 'same', 'same', '500'));
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);
        return $contents;
    }
}
