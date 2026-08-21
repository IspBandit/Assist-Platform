<?php
declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use App\Platform\DataSources\HttpClientInterface;
use App\Platform\DataSources\NativeHttpClient;
use RuntimeException;

final class GooglePlacesConnector implements ConnectorInterface
{
    public function __construct(private readonly ?HttpClientInterface $http = null) {}
    public function key(): string { return 'google_places'; }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        $apiKey = trim((string) ($credentials['api_key'] ?? ''));
        if ($apiKey === '') { throw new RuntimeException('Google Places API credentials have not been configured.'); }
        $query = trim((string) ($request['query'] ?? ''));
        $location = trim((string) ($request['location'] ?? ''));
        if ($query === '') { throw new RuntimeException('A category search query is required.'); }
        $payload = [
            'textQuery' => $query . ($location !== '' ? ' in ' . $location . ', Australia' : ' in Australia'),
            'regionCode' => (string) ($settings['region_code'] ?? 'AU'),
            'languageCode' => (string) ($settings['language_code'] ?? 'en'),
            'maxResultCount' => max(1, min(20, (int) ($request['limit'] ?? 20))),
        ];
        $response = ($this->http ?? new NativeHttpClient())->postJson(
            'https://places.googleapis.com/v1/places:searchText',
            ['X-Goog-Api-Key' => $apiKey, 'X-Goog-FieldMask' => 'places.id,places.displayName,places.formattedAddress,places.location,places.nationalPhoneNumber,places.websiteUri,places.types'],
            $payload
        );
        $decoded = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300) {
            $message = is_array($decoded) ? (string) ($decoded['error']['message'] ?? 'Google Places request failed.') : 'Google Places request failed.';
            throw new RuntimeException($message);
        }
        $results = [];
        foreach ((array) ($decoded['places'] ?? []) as $place) {
            $results[] = [
                'external_id' => (string) ($place['id'] ?? ''),
                'business_name' => (string) ($place['displayName']['text'] ?? ''),
                'formatted_address' => (string) ($place['formattedAddress'] ?? ''),
                'phone' => (string) ($place['nationalPhoneNumber'] ?? ''),
                'website' => (string) ($place['websiteUri'] ?? ''),
                'latitude' => $place['location']['latitude'] ?? null,
                'longitude' => $place['location']['longitude'] ?? null,
                'types' => array_values((array) ($place['types'] ?? [])),
                'raw' => $place,
            ];
        }
        return array_values(array_filter($results, static fn (array $row): bool => $row['external_id'] !== '' && $row['business_name'] !== ''));
    }
}

