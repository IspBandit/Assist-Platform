<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Small, deterministic traffic classifier. It is deliberately independent of
 * paid services and never blocks recognised search crawler user agents.
 */
final class BotTraffic
{
    /** @var list<string> */
    private const RECOGNISED_CRAWLERS = [
        'googlebot', 'google-inspectiontool', 'adsbot-google', 'mediapartners-google',
        'bingbot', 'bingpreview', 'duckduckbot', 'applebot', 'yandexbot', 'baiduspider',
    ];

    /** @var list<string> */
    private const ABUSIVE_AUTOMATION = [
        'python-requests', 'python-urllib', 'aiohttp', 'scrapy', 'curl/', 'wget/',
        'go-http-client', 'libwww-perl', 'httpclient', 'headlesschrome', 'phantomjs',
        'selenium', 'sqlmap', 'nikto', 'masscan', 'zgrab', 'nmap scripting engine',
    ];

    public static function isRecognisedCrawler(?string $userAgent = null): bool
    {
        $ua = self::normalise($userAgent);
        foreach (self::RECOGNISED_CRAWLERS as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function isAbusiveAutomation(?string $userAgent = null): bool
    {
        $ua = self::normalise($userAgent);
        if ($ua === '') {
            return true;
        }
        if (self::isRecognisedCrawler($ua)) {
            return false;
        }
        foreach (self::ABUSIVE_AUTOMATION as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function isBot(?string $userAgent = null): bool
    {
        $ua = self::normalise($userAgent);
        if ($ua === '') {
            return true;
        }
        if (self::isRecognisedCrawler($ua) || self::isAbusiveAutomation($ua)) {
            return true;
        }
        foreach (['bot', 'crawl', 'spider', 'slurp', 'facebookexternalhit'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function normalise(?string $userAgent): string
    {
        return strtolower(trim($userAgent ?? (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    }
}
