<?php

declare(strict_types=1);

namespace App\Services\RoadDistance;

use App\Platform\DataSources\HttpClientInterface;
use App\Platform\DataSources\NativeHttpClient;
use RuntimeException;

final class GoogleRoutesMatrixClient implements RouteMatrixClient
{
    private const ENDPOINT = 'https://routes.googleapis.com/distanceMatrix/v2:computeRouteMatrix';

    public function __construct(
        private readonly ?HttpClientInterface $http = null,
        private readonly ?string $apiKey = null,
        private readonly ?int $destinationLimit = null,
    ) {}

    public function enabled(): bool
    {
        return $this->key() !== '';
    }

    public function maxDestinations(): int
    {
        $configured = $this->destinationLimit ?? (int) env('GOOGLE_ROUTES_MAX_DESTINATIONS', 40);

        return max(1, min(100, $configured));
    }

    public function routes(float $originLatitude, float $originLongitude, array $destinations): array
    {
        $key = $this->key();
        if ($key === '' || $destinations === []) {
            return [];
        }

        $destinations = array_slice($destinations, 0, $this->maxDestinations());
        $waypoint = static fn (float $latitude, float $longitude): array => [
            'waypoint' => ['location' => ['latLng' => [
                'latitude' => $latitude,
                'longitude' => $longitude,
            ]]],
        ];
        $payload = [
            'origins' => [$waypoint($originLatitude, $originLongitude)],
            'destinations' => array_map(
                static fn (array $destination): array => $waypoint($destination['latitude'], $destination['longitude']),
                $destinations
            ),
            'travelMode' => 'DRIVE',
            // Traffic-unaware gives stable road distance and uses the lower-cost
            // Routes Essentials SKU. Live traffic is not needed for discovery.
            'routingPreference' => 'TRAFFIC_UNAWARE',
        ];

        $response = ($this->http ?? new NativeHttpClient(5))->postJson(self::ENDPOINT, [
            'X-Goog-Api-Key' => $key,
            'X-Goog-FieldMask' => 'originIndex,destinationIndex,status,condition,distanceMeters,duration',
        ], $payload);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new RuntimeException('Google Routes returned HTTP ' . $response['status'] . '.');
        }

        $decoded = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Google Routes returned an invalid response.');
        }

        $routes = [];
        foreach ($decoded as $element) {
            if (!is_array($element)
                || ($element['condition'] ?? '') !== 'ROUTE_EXISTS'
                || !isset($element['destinationIndex'], $element['distanceMeters'])) {
                continue;
            }
            $index = (int) $element['destinationIndex'];
            $duration = (string) ($element['duration'] ?? '0s');
            $routes[$index] = [
                'distance_meters' => max(0, (int) $element['distanceMeters']),
                'duration_seconds' => preg_match('/^(\d+(?:\.\d+)?)s$/', $duration, $match) === 1
                    ? (int) round((float) $match[1])
                    : 0,
            ];
        }

        return $routes;
    }

    private function key(): string
    {
        return trim($this->apiKey ?? (string) env('GOOGLE_ROUTES_API_KEY', ''));
    }
}
