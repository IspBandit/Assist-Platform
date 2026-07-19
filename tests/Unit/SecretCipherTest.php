<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Services\SecretCipher;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SecretCipherTest extends TestCase
{
    protected function setUp(): void
    {
        Config::set('app.key', 'unit-test-app-key-with-at-least-32-characters');
    }

    public function testEncryptsAndDecryptsWithRandomizedCiphertext(): void
    {
        $first = SecretCipher::encrypt('smtp-password');
        $second = SecretCipher::encrypt('smtp-password');

        self::assertNotSame($first, $second);
        self::assertTrue(SecretCipher::encrypted($first));
        self::assertSame('smtp-password', SecretCipher::decrypt($first));
        self::assertSame('smtp-password', SecretCipher::decrypt($second));
    }

    public function testRejectsTamperedCiphertext(): void
    {
        $encrypted = SecretCipher::encrypt('smtp-password');
        $offset = strlen('enc:v1:') + 10;
        $tampered = substr_replace($encrypted, $encrypted[$offset] === 'A' ? 'B' : 'A', $offset, 1);

        $this->expectException(RuntimeException::class);
        SecretCipher::decrypt($tampered);
    }

    public function testReadsLegacyPlaintextForExplicitMigration(): void
    {
        self::assertSame('legacy-secret', SecretCipher::decrypt('legacy-secret'));
    }
}
