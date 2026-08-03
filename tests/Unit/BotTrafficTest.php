<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\BotTraffic;
use PHPUnit\Framework\TestCase;

final class BotTrafficTest extends TestCase
{
    public function testRecognisedSearchCrawlersAreNotBlocked(): void
    {
        self::assertTrue(BotTraffic::isRecognisedCrawler('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        self::assertFalse(BotTraffic::isAbusiveAutomation('Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)'));
        self::assertFalse(BotTraffic::isAbusiveAutomation('Mozilla/5.0 (compatible; bingbot/2.0)'));
    }

    public function testObviousAutomationIsBlockedAndExcluded(): void
    {
        foreach (['curl/8.5.0', 'python-requests/2.32', 'sqlmap/1.8', 'HeadlessChrome/124.0'] as $ua) {
            self::assertTrue(BotTraffic::isAbusiveAutomation($ua), $ua);
            self::assertTrue(BotTraffic::isBot($ua), $ua);
        }
        self::assertTrue(BotTraffic::isAbusiveAutomation(''));
    }

    public function testNormalBrowserIsNotClassifiedAsBot(): void
    {
        $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/126.0 Safari/537.36';
        self::assertFalse(BotTraffic::isBot($ua));
        self::assertFalse(BotTraffic::isAbusiveAutomation($ua));
    }
}
