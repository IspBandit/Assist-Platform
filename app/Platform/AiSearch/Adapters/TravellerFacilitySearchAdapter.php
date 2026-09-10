<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Core\Database;
use App\Helpers\Geo;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use Throwable;

/**
 * Searches canonical traveller_facilities (AI-6). Never writes to caravan_parks (ADR 0032).
 */
final class TravellerFacilitySearchAdapter implements FacilitySearchPort
{
    public function __construct(private readonly ?StayFacilitySearchBridge $stayFacilities = null) {}

    /**
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    public function search(
        Intent $intent,
        ?array $town = null,
        ?float $lat = null,
        ?float $lng = null,
        ?int $brandId = null,
    ): array {
        if (!TravellerFacilitiesFeature::enabled() || $brandId === null || $brandId <= 0) {
            return [];
        }
        $types = $intent->facilityTypeKeys;
        if ($types === []) {
            return [];
        }

        $radius = $intent->radiusKm ?? (int) config('ai_search.default_radius_km', 25);
        $hasOrigin = $lat !== null && $lng !== null;
        if (!$hasOrigin && ($town === null || !isset($town['id']))) {
            return [];
        }

        try {
            if ($hasOrigin) {
                $rows = $this->nearTypes($types, (float) $lat, (float) $lng, $radius, $brandId);
            } elseif ($town !== null && isset($town['id'])) {
                $rows = $this->forTown($types, (int) $town['id'], $brandId);
            } else {
                // Named or coordinate origin is mandatory. Never fall back to a
                // nationwide facility dump when location resolution failed.
                return [];
            }
        } catch (Throwable) {
            return [];
        }

        $list = [];
        foreach ($rows as $row) {
            $mapped = $this->mapRow($row);
            if ($mapped !== null) {
                $list[] = $mapped;
            }
        }

        if ($hasOrigin) {
            $list = array_merge($list, ($this->stayFacilities ?? new StayFacilitySearchBridge())->search(
                $types,
                (float) $lat,
                (float) $lng,
                $radius,
            ));
        }

        if ($hasOrigin) {
            $filter = ['scope' => 'km', 'km' => $radius, 'town_radius_km' => (int) config('geo.default_town_radius_km', 20)];
            $list = Geo::applyDistanceFilter($list, $lat, $lng, $filter, $town !== null ? (int) $town['id'] : null);
        }

        return $list;
    }

    /**
     * @param list<string> $types
     * @return list<array<string,mixed>>
     */
    private function nearTypes(array $types, float $latitude, float $longitude, int $radiusKm, int $brandId): array
    {
        $radiusKm = max(1, min(500, $radiusKm));
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $latDelta = $radiusKm / 111.32;
        $longitudeScale = max(0.01, abs(cos(deg2rad($latitude))));
        $lngDelta = min(180.0, $radiusKm / (111.32 * $longitudeScale));

        $sql = 'SELECT f.id, f.facility_type, f.name, f.slug, f.latitude, f.longitude, f.formatted_address,
                       f.locality, f.operating_status, f.opening_hours, f.source_key, f.source_record_id,
                       f.source_licence, f.source_attribution, f.source_url, f.confidence, f.verification_status,
                       t.name AS town_name, s.abbreviation AS state_abbr,
                       f.latitude AS town_lat, f.longitude AS town_lng,
                       (6371 * ACOS(LEAST(1, GREATEST(-1,
                           COS(RADIANS(?)) * COS(RADIANS(f.latitude)) * COS(RADIANS(f.longitude) - RADIANS(?))
                           + SIN(RADIANS(?)) * SIN(RADIANS(f.latitude))
                       )))) AS distance_km
                FROM traveller_facilities f
                LEFT JOIN towns t ON t.id = f.town_id
                LEFT JOIN states s ON s.id = f.state_id
                WHERE f.deleted_at IS NULL AND f.status = \'active\'
                  AND f.verification_status IN (\'reviewed\', \'verified\')
                  AND f.brand_id = ?
                  AND f.facility_type IN (' . $placeholders . ')
                  AND f.latitude IS NOT NULL AND f.longitude IS NOT NULL
                  AND f.latitude BETWEEN ? AND ?
                  AND f.longitude BETWEEN ? AND ?
                HAVING distance_km <= ?
                ORDER BY distance_km ASC, f.name
                LIMIT 120';

        $params = array_merge(
            [$latitude, $longitude, $latitude, $brandId],
            $types,
            [$latitude - $latDelta, $latitude + $latDelta, $longitude - $lngDelta, $longitude + $lngDelta, $radiusKm]
        );

        return Database::select($sql, $params);
    }

    /**
     * @param list<string> $types
     * @return list<array<string,mixed>>
     */
    private function forTown(array $types, int $townId, int $brandId): array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        return Database::select(
            'SELECT f.id, f.facility_type, f.name, f.slug, f.latitude, f.longitude, f.formatted_address,
                    f.locality, f.operating_status, f.opening_hours, f.source_key, f.source_record_id,
                    f.source_licence, f.source_attribution, f.source_url, f.confidence, f.verification_status,
                    t.name AS town_name, s.abbreviation AS state_abbr,
                    f.latitude AS town_lat, f.longitude AS town_lng
             FROM traveller_facilities f
             LEFT JOIN towns t ON t.id = f.town_id
             LEFT JOIN states s ON s.id = f.state_id
             WHERE f.deleted_at IS NULL AND f.status = \'active\'
               AND f.verification_status IN (\'reviewed\', \'verified\')
               AND f.brand_id = ?
               AND f.facility_type IN (' . $placeholders . ')
               AND (
                    f.town_id = ?
                    OR (
                        f.town_id IS NULL
                        AND f.locality IS NOT NULL
                        AND LOWER(f.locality) = (SELECT LOWER(name) FROM towns WHERE id = ? LIMIT 1)
                    )
               )
             ORDER BY f.name
             LIMIT 120',
            array_merge([$brandId], $types, [$townId, $townId])
        );
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>|null
     */
    private function mapRow(array $row): ?array
    {
        $id = (int) ($row['id'] ?? 0);
        $name = trim((string) ($row['name'] ?? ''));
        if ($id <= 0 || $name === '') {
            return null;
        }

        $verification = (string) ($row['verification_status'] ?? 'unverified');
        $origin = $verification === 'verified'
            ? ResultProvenance::ORIGIN_CANONICAL
            : ResultProvenance::ORIGIN_IMPORTED;
        $confidence = isset($row['confidence']) ? ((int) $row['confidence']) / 100.0 : null;

        $mapped = [
            'id' => $id,
            'name' => $name,
            'business_name' => $name,
            'slug' => (string) ($row['slug'] ?? ''),
            'facility_type' => (string) ($row['facility_type'] ?? ''),
            'formatted_address' => $row['formatted_address'] ?? null,
            'locality' => $row['locality'] ?? null,
            'town_name' => $row['town_name'] ?? null,
            'state_abbr' => $row['state_abbr'] ?? null,
            'town_lat' => isset($row['town_lat']) ? (float) $row['town_lat'] : null,
            'town_lng' => isset($row['town_lng']) ? (float) $row['town_lng'] : null,
            'latitude' => isset($row['latitude']) ? (float) $row['latitude'] : null,
            'longitude' => isset($row['longitude']) ? (float) $row['longitude'] : null,
            'operating_status' => $row['operating_status'] ?? null,
            'opening_hours' => $row['opening_hours'] ?? null,
            'distance_km' => isset($row['distance_km']) ? (float) $row['distance_km'] : null,
            'source_url' => $this->safeHttpUrl($row['source_url'] ?? null),
        ];

        return ResultProvenance::annotate(
            $mapped,
            $origin,
            (string) ($row['source_key'] ?? 'traveller_facilities'),
            isset($row['source_record_id']) ? (string) $row['source_record_id'] : null,
            isset($row['source_licence']) ? (string) $row['source_licence'] : null,
            isset($row['source_attribution']) ? (string) $row['source_attribution'] : null,
            $confidence
        );
    }

    private function safeHttpUrl(mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        $url = trim($value);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($scheme, ['http', 'https'], true) ? $url : null;
    }
}
