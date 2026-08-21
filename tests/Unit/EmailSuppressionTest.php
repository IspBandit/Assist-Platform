<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Services\EmailSuppression;
use PHPUnit\Framework\TestCase;

final class EmailSuppressionTest extends TestCase
{
    protected function setUp(): void
    {
        Config::set('app.key', 'unit-test-email-preference-key-32-chars');
        Config::set('app.url', 'https://vanassist.example');
    }

    protected function tearDown(): void
    {
        Config::set('app.url', 'http://localhost');
        Config::set('app.key', '');
    }

    public function testSignedPreferenceLinkVerifiesOnlyItsAddress(): void
    {
        $url = EmailSuppression::unsubscribeUrl(' Person@Example.com ');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertSame('person@example.com', $query['email']);
        self::assertTrue(EmailSuppression::verify((string) $query['email'], (string) $query['signature']));
        self::assertFalse(EmailSuppression::verify('other@example.com', (string) $query['signature']));
    }

    public function testDirectoryNoticePreferenceUsesASeparateSignaturePurpose(): void
    {
        $url = EmailSuppression::directoryNoticeOptOutUrl('Provider@Example.com');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        self::assertTrue(EmailSuppression::verifyDirectoryNotice((string) $query['email'], (string) $query['signature']));
        self::assertFalse(EmailSuppression::verify((string) $query['email'], (string) $query['signature']));
    }
}
