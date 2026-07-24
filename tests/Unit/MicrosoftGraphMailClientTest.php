<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\MicrosoftGraphMailClient;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class MicrosoftGraphMailClientTest extends TestCase
{
    public function testSendEndpointTargetsImmutableBrandMailbox(): void
    {
        $method = new ReflectionMethod(MicrosoftGraphMailClient::class, 'sendingEndpoint');

        $this->assertSame(
            'https://graph.microsoft.com/v1.0/users/support%40towsmart.com.au/sendMail',
            $method->invoke(null, 'support@towsmart.com.au')
        );
    }
}
