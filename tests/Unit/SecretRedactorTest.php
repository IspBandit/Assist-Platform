<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\SecretRedactor;
use PHPUnit\Framework\TestCase;

final class SecretRedactorTest extends TestCase
{
    public function testRedactsJsonBearerAndJwtCredentials(): void
    {
        $input = '{"access_token":"eyJheader.payload.signature"} Bearer eyJother.payload.signature';
        $redacted = SecretRedactor::redact($input);

        self::assertStringNotContainsString('eyJheader', $redacted);
        self::assertStringNotContainsString('eyJother', $redacted);
        self::assertStringContainsString('[REDACTED]', $redacted);
    }

    public function testRedactsSensitiveContextKeysRecursively(): void
    {
        $context = SecretRedactor::context(['nested' => ['client_secret' => 'secret'], 'message' => 'safe']);

        self::assertSame('[REDACTED]', $context['nested']['client_secret']);
        self::assertSame('safe', $context['message']);
    }
}
