<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use App\Platform\DataSources\FacilityTypeMapper;
use RuntimeException;

/**
 * GeoJSON FeatureCollection connector for government datasets (DATA-012).
 */
final class GeoJsonDatasetConnector implements ConnectorInterface
{
    public function key(): string
    {
        return 'gov_geojson';
    }

    public function search(array $request, array $credentials, array $settings = []): array
    {
        unset($credentials);
        $payload = (string) ($request['payload'] ?? '');
        if ($payload === '' && !empty($request['path']) && is_string($request['path']) && is_file($request['path'])) {
            $payload = (string) file_get_contents($request['path']);
        }
        if (trim($payload) === '') {
            throw new RuntimeException('GeoJSON connector requires payload or path.');
        }
        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('GeoJSON payload is invalid JSON.');
        }
        $features = $decoded['features'] ?? (isset($decoded['type']) && $decoded['type'] === 'Feature' ? [$decoded] : []);
        if (!is_array($features)) {
            throw new RuntimeException('GeoJSON FeatureCollection missing features.');
        }

        $limit = max(1, min(1000, (int) ($request['limit'] ?? 500)));
        $defaultType = FacilityTypeMapper::normalise((string) ($settings['default_facility_type'] ?? 'other_essential'));
        $nameField = (string) ($settings['name_field'] ?? 'name');
        $typeField = (string) ($settings['type_field'] ?? 'facility_type');
        $idField = (string) ($settings['id_field'] ?? 'id');

        $rows = [];
        foreach ($features as $index => $feature) {
            if (!is_array($feature)) {
                continue;
            }
            $props = (array) ($feature['properties'] ?? []);
            $geom = (array) ($feature['geometry'] ?? []);
            $coords = (array) ($geom['coordinates'] ?? []);
            $lng = null;
            $lat = null;
            if (($geom['type'] ?? '') === 'Point' && count($coords) >= 2) {
                $lng = $coords[0] ?? null;
                $lat = $coords[1] ?? null;
            }
            $name = trim((string) ($props[$nameField] ?? $props['Name'] ?? $props['title'] ?? ''));
            $externalId = trim((string) ($props[$idField] ?? $feature['id'] ?? ''));
            if ($externalId === '') {
                $externalId = 'geojson-' . md5(json_encode($feature) ?: (string) $index);
            }
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'external_id' => $externalId,
                'business_name' => $name,
                'name' => $name,
                'facility_type' => FacilityTypeMapper::normalise((string) ($props[$typeField] ?? ''), $defaultType),
                'formatted_address' => (string) ($props['address'] ?? $props['formatted_address'] ?? ''),
                'locality' => (string) ($props['locality'] ?? $props['suburb'] ?? $props['town'] ?? ''),
                'latitude' => is_numeric($lat) ? (float) $lat : (is_numeric($props['latitude'] ?? null) ? (float) $props['latitude'] : null),
                'longitude' => is_numeric($lng) ? (float) $lng : (is_numeric($props['longitude'] ?? null) ? (float) $props['longitude'] : null),
                'source_url' => (string) ($settings['source_url'] ?? ''),
                'licence' => (string) ($settings['licence'] ?? ''),
                'attribution' => (string) ($settings['attribution'] ?? ''),
                'raw' => $feature,
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }
        return $rows;
    }
}
