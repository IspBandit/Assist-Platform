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
}
