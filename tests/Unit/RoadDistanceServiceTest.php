<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\RoadDistance\RoadDistanceService;
use App\Services\RoadDistance\RouteMatrixClient;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class RoadDistanceServiceTest extends TestCase
{
    public function testItDeduplicatesCoordinatesAndEnforcesRadiusUsingRoadDistance(): void
    {
        $client = new class implements RouteMatrixClient {
            public int $destinationCount = 0;
            public function enabled(): bool { return true; }
            public function maxDestinations(): int { return 40; }
            public function routes(float $originLatitude, float $originLongitude, array $destinations): array
            {
                $this->destinationCount = count($destinations);
                return [
                    0 => ['distance_meters' => 12000, 'duration_seconds' => 900],
                    1 => ['distance_meters' => 31000, 'duration_seconds' => 1800],
                ];
            }
        };
        $service = new RoadDistanceService($client);

        $result = $service->enrichGroups([
            'providers' => [
                ['id' => 1, 'business_name' => 'Provider', 'latitude' => -27.5, 'longitude' => 153.0, 'distance_basis' => 'provider_point', 'distance_km' => 9],
            ],
            'facilities' => [
                ['id' => 2, 'name' => 'Same point', 'latitude' => -27.5, 'longitude' => 153.0, 'distance_km' => 9],
                ['id' => 3, 'name' => 'Road too far', 'latitude' => -27.6, 'longitude' => 153.1, 'distance_km' => 20],
            ],
        ], -27.4, 153.0, 25);

        self::assertSame(2, $client->destinationCount, 'The shared coordinate must use one billable matrix element.');
        self::assertCount(1, $result['providers']);
        self::assertCount(1, $result['facilities']);
        self::assertSame(12.0, $result['providers'][0]['distance_km']);
        self::assertSame('road', $result['providers'][0]['distance_metric']);
        self::assertSame(900, $result['providers'][0]['drive_time_seconds']);
        self::assertSame('12 km by road · about 15 min', RoadDistanceService::displayLabel($result['providers'][0]));
    }

    public function testTownCentreProviderNeverClaimsAnExactRoadDistanceOrConsumesARouteElement(): void
    {
        $client = new class implements RouteMatrixClient {
            public int $destinationCount = 0;
            public function enabled(): bool { return true; }
            public function maxDestinations(): int { return 40; }
            public function routes(float $originLatitude, float $originLongitude, array $destinations): array
            {
                $this->destinationCount = count($destinations);
                return [0 => ['distance_meters' => 8000, 'duration_seconds' => 600]];
            }
        };

        $result = (new RoadDistanceService($client))->enrichGroups(['providers' => [
            ['id' => 1, 'business_name' => 'Exact workshop', 'latitude' => -27.5, 'longitude' => 153.0, 'distance_basis' => 'provider_point', 'distance_km' => 7],
            ['id' => 2, 'business_name' => 'Locality only', 'town_lat' => -27.6, 'town_lng' => 153.1, 'distance_basis' => 'town_centre', 'distance_km' => 15],
        ]], -27.4, 153.0, 25);

        self::assertSame(1, $client->destinationCount);
        self::assertCount(2, $result['providers']);
        self::assertSame('road', $result['providers'][0]['distance_metric']);
        self::assertSame('location_estimate', $result['providers'][1]['distance_metric']);
        self::assertArrayNotHasKey('distance_km', $result['providers'][1]);
        self::assertSame(
            'Exact provider distance unavailable (town-centre estimate)',
            RoadDistanceService::displayLabel($result['providers'][1])
        );
    }

    public function testItKeepsStraightLineResultsWhenGoogleIsTemporarilyUnavailable(): void
    {
        $client = new class implements RouteMatrixClient {
            public function enabled(): bool { return true; }
            public function maxDestinations(): int { return 40; }
            public function routes(float $originLatitude, float $originLongitude, array $destinations): array
            {
                throw new RuntimeException('outage');
            }
        };
        $groups = ['providers' => [[
            'id' => 1, 'business_name' => 'Provider', 'town_lat' => -27.5, 'town_lng' => 153.0, 'distance_km' => 9,
        ]]];

        $result = (new RoadDistanceService($client))->enrichGroups($groups, -27.4, 153.0, 25);

        self::assertSame($groups, $result);
        self::assertSame('9.0 km straight-line', RoadDistanceService::displayLabel($result['providers'][0]));
    }
}
