<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SmtpClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class SmtpClientTest extends TestCase
{
    public function testRejectsHeaderInjectionBeforeConnecting(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('prohibited control characters');

        SmtpClient::send(
            $this->config(),
            'recipient@example.com',
            'Recipient',
            "Expected subject\r\nBcc: attacker@example.com",
            '<p>Hello</p>',
            'Hello'
        );
    }

    public function testRejectsInvalidRecipientBeforeConnecting(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('recipient address is invalid');

        SmtpClient::send(
            $this->config(),
            'not-an-email',
            'Recipient',
            'Expected subject',
            '<p>Hello</p>',
            'Hello'
        );
    }

    /** @return array<string,mixed> */
    private function config(): array
    {
        return [
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'sender@example.com',
            'password' => 'test-only',
            'from_address' => 'sender@example.com',
            'from_name' => 'Assist Platform',
        ];
    }
}
