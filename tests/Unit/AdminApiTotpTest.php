<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiTotp;
use PHPUnit\Framework\TestCase;

final class AdminApiTotpTest extends TestCase
{
    public function testGenerateSecretIsBase32(): void
    {
        $secret = AdminApiTotp::generateSecret();
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $secret);
        self::assertGreaterThanOrEqual(16, strlen($secret));
    }

    public function testVerifyAcceptsCurrentCode(): void
    {
        $secret = AdminApiTotp::generateSecret();
        $now = 1_700_000_000;
        $code = AdminApiTotp::codeAt($secret, intdiv($now, 30));

        self::assertTrue(AdminApiTotp::verify($code, $secret, 1, $now));
        self::assertFalse(AdminApiTotp::verify('000000', $secret, 1, $now));
        self::assertFalse(AdminApiTotp::verify('abcdef', $secret, 1, $now));
    }

    public function testProvisioningUriContainsSecret(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $uri = AdminApiTotp::provisioningUri($secret, 'admin@example.test', 'Assist Platform Admin API');

        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('secret=' . $secret, $uri);
        self::assertStringContainsString('issuer=Assist%20Platform%20Admin%20API', $uri);
    }

    public function testBase32RoundTrip(): void
    {
        $raw = 'HelloMFA!';
        $encoded = AdminApiTotp::base32Encode($raw);
        self::assertSame($raw, AdminApiTotp::base32Decode($encoded));
    }
}
