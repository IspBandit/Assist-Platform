<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Platform\DataSources\HttpClientInterface;
use App\Services\RoadDistance\GoogleRoutesCredentialProvisioner;
use App\Services\RoadDistance\GoogleRoutesMatrixClient;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GoogleRoutesMatrixClientTest extends TestCase
{
    public function testReleaseProvisioningRejectsInvalidEnvelopeMetadataBeforeDatabaseAccess(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('metadata is invalid');

        (new GoogleRoutesCredentialProvisioner())->provisionForRelease(
            'AIza' . str_repeat('x', 35),
            'not-a-release',
            str_repeat('a', 64)
        );
    }

    public function testProvisionerRejectsAnythingThatIsNotAGoogleApiKeyBeforeDatabaseAccess(): void
    {
        $this->expectException(InvalidArgumentException::class);
        (new GoogleRoutesCredentialProvisioner())->provision('not-a-google-key');
    }

    public function testItRequestsDrivingMatrixAndParsesDistanceAndDuration(): void
    {
        $http = new class implements HttpClientInterface {
            /** @var array<string,mixed> */
            public array $payload = [];
            /** @var array<string,string> */
            public array $headers = [];

            public function postJson(string $url, array $headers, array $payload): array
            {
                \PHPUnit\Framework\Assert::assertSame('https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix', $url);
                $this->headers = $headers;
                $this->payload = $payload;

                return ['status' => 200, 'body' => json_encode([
                    ['originIndex' => 0, 'destinationIndex' => 0, 'condition' => 'ROUTE_EXISTS', 'distanceMeters' => 15469, 'duration' => '1142s'],
                ], JSON_THROW_ON_ERROR)];
            }
        };
        $client = new GoogleRoutesMatrixClient($http, 'test-key', 40);

        $routes = $client->routes(-27.4698, 153.0251, [
            ['latitude' => -27.5598, 'longitude' => 153.0810],
        ]);

        self::assertSame('DRIVE', $http->payload['travelMode']);
        self::assertSame('TRAFFIC_UNAWARE', $http->payload['routingPreference']);
        self::assertSame('test-key', $http->headers['X-Goog-Api-Key']);
        self::assertSame(['distance_meters' => 15469, 'duration_seconds' => 1142], $routes[0]);
    }
}
