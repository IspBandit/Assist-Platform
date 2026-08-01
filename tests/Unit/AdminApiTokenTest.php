<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Api\AdminApiToken;
use PHPUnit\Framework\TestCase;

final class AdminApiTokenTest extends TestCase
{
    public function testGenerateProducesUrlSafeOpaqueToken(): void
    {
        $token = AdminApiToken::generate();
        self::assertGreaterThanOrEqual(40, strlen($token));
        self::assertDoesNotMatchRegularExpression('/[+\/=\s]/', $token);
        self::assertNotSame($token, AdminApiToken::generate());
    }

    public function testHashIsSha256Hex(): void
    {
        $hash = AdminApiToken::hash('example-token');
        self::assertSame(64, strlen($hash));
        self::assertSame(hash('sha256', 'example-token'), $hash);
    }

    public function testUuidIsVersion4Format(): void
    {
        $uuid = AdminApiToken::uuid();
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
        self::assertNotSame($uuid, AdminApiToken::uuid());
    }
}
