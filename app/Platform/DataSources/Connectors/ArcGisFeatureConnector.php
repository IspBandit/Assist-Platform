<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use App\Platform\DataSources\FacilityTypeMapper;
use App\Platform\DataSources\SimpleHttpClient;
use RuntimeException;

/**
 * ArcGIS Feature Service query connector (DATA-012).
 * Settings: feature_url (layer query endpoint base), optional where, out_fields.
 */
final class ArcGisFeatureConnector implements ConnectorInterface
{
    public function __construct(private readonly ?SimpleHttpClient $http = null)
    {
    }

    public function key(): string
    {
        return 'gov_arcgis';
    }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        unset($credentials);
        $base = trim((string) ($settings['feature_url'] ?? $request['feature_url'] ?? ''));
        if ($base === '') {
            throw new RuntimeException('ArcGIS connector requires feature_url.');
        }
        $limit = max(1, min(500, (int) ($request['limit'] ?? 100)));
        $where = trim((string) ($settings['where'] ?? '1=1'));
        $outFields = trim((string) ($settings['out_fields'] ?? '*'));
        $defaultType = FacilityTypeMapper::normalise((string) ($settings['default_facility_type'] ?? 'other_essential'));
        $nameField = (string) ($settings['name_field'] ?? 'name');
        $typeField = (string) ($settings['type_field'] ?? 'facility_type');
        $idField = (string) ($settings['id_field'] ?? 'OBJECTID');

        $query = http_build_query([
            'where' => $where !== '' ? $where : '1=1',
            'outFields' => $outFields !== '' ? $outFields : '*',
            'returnGeometry' => 'true',
            'outSR' => '4326',
            'f' => 'json',
            'resultRecordCount' => $limit,
        ]);
        $url = rtrim($base, '/') . '/query?' . $query;
        $http = $this->http ?? new SimpleHttpClient();
        $response = $http->get($url);
        $decoded = json_decode($response['body'], true);
        if ($response['status'] < 200 || $response['status'] >= 300 || !is_array($decoded)) {
            throw new RuntimeException('ArcGIS feature query failed.');
        }
        if (!empty($decoded['error'])) {
            throw new RuntimeException((string) ($decoded['error']['message'] ?? 'ArcGIS error'));
        }

        $rows = [];
        foreach ((array) ($decoded['features'] ?? []) as $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $attrs = (array) ($feature['attributes'] ?? []);
            $geom = (array) ($feature['geometry'] ?? []);
            $lat = $geom['y'] ?? $attrs['latitude'] ?? $attrs['lat'] ?? null;
            $lng = $geom['x'] ?? $attrs['longitude'] ?? $attrs['lng'] ?? $attrs['lon'] ?? null;
            $name = trim((string) ($attrs[$nameField] ?? $attrs['Name'] ?? $attrs['TITLE'] ?? ''));
            $externalId = trim((string) ($attrs[$idField] ?? $attrs['objectid'] ?? $attrs['id'] ?? ''));
            if ($name === '' || $externalId === '') {
                continue;
            }
            $type = FacilityTypeMapper::normalise((string) ($attrs[$typeField] ?? ''), $defaultType);
            $rows[] = [
                'external_id' => $externalId,
                'business_name' => $name,
                'name' => $name,
                'facility_type' => $type,
                'formatted_address' => (string) ($attrs['address'] ?? $attrs['Address'] ?? ''),
                'locality' => (string) ($attrs['locality'] ?? $attrs['suburb'] ?? $attrs['town'] ?? ''),
                'latitude' => is_numeric($lat) ? (float) $lat : null,
                'longitude' => is_numeric($lng) ? (float) $lng : null,
                'source_url' => $base,
                'licence' => (string) ($settings['licence'] ?? ''),
                'attribution' => (string) ($settings['attribution'] ?? ''),
                'raw' => $feature,
            ];
        }
        return $rows;
    }
}
