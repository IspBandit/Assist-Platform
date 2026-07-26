<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MicrosoftGraphMailClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MicrosoftGraphMailClientTest extends TestCase
{
    public function testSendEndpointTargetsConfiguredTransportMailbox(): void
    {
        $method = new ReflectionMethod(MicrosoftGraphMailClient::class, 'sendingEndpoint');

        $this->assertSame(
            'https://graph.microsoft.com/v1.0/users/operations%40vanassist.com.au/sendMail',
            $method->invoke(null, 'operations@vanassist.com.au')
        );
    }

    public function testConfiguredOperationsMailboxOverridesBrandAlias(): void
    {
        $method = new ReflectionMethod(MicrosoftGraphMailClient::class, 'sendingMailbox');

        $this->assertSame(
            'operations@vanassist.com.au',
            $method->invoke(null, ['graph_mailbox' => 'operations@vanassist.com.au'], 'support@towsmart.com.au')
        );
    }

    public function testFallbackUsesRealMailboxAsFromAndBrandAliasAsReplyTo(): void
    {
        $method = new ReflectionMethod(MicrosoftGraphMailClient::class, 'messagePayload');
        $payload = $method->invoke(
            null,
            ['from_name' => 'TowSmart'],
            'operations@vanassist.com.au',
            'support@towsmart.com.au',
            'customer@example.com',
            'Customer',
            'Test',
            '<p>Test</p>',
            'Test'
        );

        $this->assertSame(
            'operations@vanassist.com.au',
            $payload['message']['from']['emailAddress']['address']
        );
        $this->assertSame(
            'support@towsmart.com.au',
            $payload['message']['replyTo'][0]['emailAddress']['address']
        );
    }
}
