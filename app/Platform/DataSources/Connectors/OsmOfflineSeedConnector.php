<?php

declare(strict_types=1);

namespace App\Platform\DataSources\Connectors;

use App\Platform\DataSources\ConnectorInterface;
use App\Services\OsmRefreshService;
use RuntimeException;

/**
 * Offline OSM seed connector (Ask/dataset path).
 * Reads managed AU seed / server extract only — never calls live Overpass.
 */
final class OsmOfflineSeedConnector implements ConnectorInterface
{
    public const KEY = 'osm_offline_seed';

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param array{query?:string,location?:string,limit?:int,state?:string} $request
     * @return list<array<string,mixed>>
     */
    public function search(array $request, array $credentials, array $settings = []): array
    {
        unset($credentials);
        if (!(bool) config('ai_search.osm_offline_enabled', false)
            && !(bool) ($settings['force'] ?? false)) {
            throw new RuntimeException(
                'OSM offline seed connector is disabled. Enable ai_search.osm_offline_enabled or pass force for admin staging.'
            );
        }

        $path = trim((string) ($settings['seed_path'] ?? ''));
        if ($path === '') {
            $path = (string) (config('ai_search.osm_seed_path') ?: '');
        }
        if ($path === '' || !is_file($path)) {
            $resolved = OsmRefreshService::resolveSeedPath();
            $path = $resolved ?? '';
        }
        if ($path === '' || !is_file($path)) {
            return [];
        }

        $raw = json_decode((string) file_get_contents($path), true);
        if (!is_array($raw)) {
            return [];
        }
        $businesses = $raw['businesses'] ?? $raw;
        if (!is_array($businesses)) {
            return [];
        }

        $query = mb_strtolower(trim((string) ($request['query'] ?? '')));
        $state = strtoupper(trim((string) ($request['state'] ?? ($settings['state'] ?? ''))));
        $limit = max(1, min(500, (int) ($request['limit'] ?? ($settings['limit'] ?? 100))));

        $hits = [];
        foreach ($businesses as $row) {
            if (!is_array($row)) {
                continue;
            }
            $name = trim((string) ($row['name'] ?? $row['business_name'] ?? ''));
            $externalId = trim((string) ($row['id'] ?? $row['external_id'] ?? ''));
            if ($name === '' || $externalId === '') {
                continue;
            }
            if ($state !== '' && strtoupper(trim((string) ($row['state'] ?? ''))) !== $state) {
                continue;
            }
            if ($query !== '') {
                $hay = mb_strtolower(implode(' ', [
                    $name,
                    (string) ($row['town'] ?? ''),
                    (string) ($row['address'] ?? ''),
                    (string) ($row['services'] ?? ''),
                    implode(' ', array_map('strval', (array) ($row['cats'] ?? []))),
                ]));
                if (!str_contains($hay, $query)) {
                    continue;
                }
            }

            $hits[] = [
                'external_id' => $externalId,
                'business_name' => $name,
                'formatted_address' => trim(implode(', ', array_filter([
                    (string) ($row['address'] ?? ''),
                    (string) ($row['town'] ?? ''),
                    (string) ($row['state'] ?? ''),
                ]))),
                'phone' => (string) ($row['phone'] ?? ''),
                'website' => (string) ($row['website'] ?? ''),
                'latitude' => isset($row['lat']) && is_numeric($row['lat']) ? (float) $row['lat'] : null,
                'longitude' => isset($row['lng']) && is_numeric($row['lng']) ? (float) $row['lng'] : null,
                'confidence' => 55,
                'raw' => $row,
            ];
            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }
}
