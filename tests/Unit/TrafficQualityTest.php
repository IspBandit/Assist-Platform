<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Demand\TrafficQuality;
use PHPUnit\Framework\TestCase;

final class TrafficQualityTest extends TestCase
{
    public function testInternalNavigationWithoutPersistedCookieIsUnattributable(): void
    {
        self::assertTrue(TrafficQuality::isUnattributableInternalNavigation('internal', false));
        self::assertFalse(TrafficQuality::isUnattributableInternalNavigation('internal', true));
        self::assertFalse(TrafficQuality::isUnattributableInternalNavigation('direct', false));
        self::assertFalse(TrafficQuality::isUnattributableInternalNavigation('google.com', false));
    }

    public function testEligibleSessionSqlRejectsInvalidAliasesAndSuspectInternalSessions(): void
    {
        $sql = TrafficQuality::eligibleSessionSql('ts');
        self::assertStringContainsString('ts.is_bot=0', $sql);
        self::assertStringContainsString("ts.referral_source='internal'", $sql);
        self::assertStringContainsString('ts.first_seen_at=ts.last_seen_at', $sql);

        $this->expectException(\InvalidArgumentException::class);
        TrafficQuality::eligibleSessionSql('ts; DROP TABLE page_views');
    }
}
