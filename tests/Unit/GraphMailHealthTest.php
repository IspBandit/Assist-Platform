<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\GraphMailHealth;
use PHPUnit\Framework\TestCase;

final class GraphMailHealthTest extends TestCase
{
    public function testMissingCertificateFailsClosedWithoutExposingPaths(): void
    {
        $result = GraphMailHealth::inspect([
            'driver' => 'graph',
            'graph' => [
                'mailbox' => 'operations@example.com',
                'certificate_path' => '/not-present/mail.crt',
                'private_key_path' => '/not-present/mail.key',
            ],
        ]);

        $this->assertSame('missing', $result['status']);
        $this->assertFalse($result['certificate_present']);
        $this->assertFalse($result['private_key_readable']);
        $this->assertArrayNotHasKey('certificate_path', $result);
        $this->assertArrayNotHasKey('private_key_path', $result);
    }
}
