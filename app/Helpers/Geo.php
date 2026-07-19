<?php

declare(strict_types=1);

namespace App\Helpers;

/**
 * Great-circle distance helpers for provider search and listings.
 */
final class Geo
{
    /** Allowed max-distance filter values (km). */
    public const DISTANCE_OPTIONS = [25, 50, 100, 200, 500];

    public const SCOPE_TOWN = 'town';
    public const SCOPE_ANY = 'any';

    /**
     * Resolve the distance filter from the request.
     *
     * When a town is known and no filter was submitted, default to "this town"
     * (providers based in the nearest/searched town).
     *
     * @return array{scope:string,km:?int,town_radius_km:int}
     */
    public static function resolveDistanceFilter(mixed $raw, bool $hasTown): array
    {
        $townRadius = (int) config('geo.default_town_radius_km', 20);

        if ($raw === null && $hasTown) {
            return ['scope' => self::SCOPE_TOWN, 'km' => null, 'town_radius_km' => $townRadius];
        }

        $value = is_scalar($raw) ? trim((string) $raw) : '';
        if ($value === '' || $value === self::SCOPE_ANY) {
            return ['scope' => self::SCOPE_ANY, 'km' => null, 'town_radius_km' => $townRadius];
        }

        if ($value === self::SCOPE_TOWN) {
            return $hasTown
                ? ['scope' => self::SCOPE_TOWN, 'km' => null, 'town_radius_km' => $townRadius]
                : ['scope' => self::SCOPE_ANY, 'km' => null, 'town_radius_km' => $townRadius];
        }

        $km = self::parseMaxDistance($value);

        return ['scope' => $km !== null ? 'km' : self::SCOPE_ANY, 'km' => $km, 'town_radius_km' => $townRadius];
    }

    /** Great-circle distance in kilometres, rounded to nearest km. */
    public static function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return (float) round($earth * 2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    /** @return float|null Rounded km, or null when coordinates are missing. */
    public static function distanceKm(?float $originLat, ?float $originLng, mixed $targetLat, mixed $targetLng): ?float
    {
        if ($originLat === null || $originLng === null || $targetLat === null || $targetLng === null) {
            return null;
        }

        return self::haversineKm($originLat, $originLng, (float) $targetLat, (float) $targetLng);
    }

    /**
     * Keep only providers that directly serve the town (not wider regional matches).
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function filterDirectTownProviders(array $rows): array
    {
        return array_values(array_filter(
            $rows,
            static fn (array $row): bool => !isset($row['relevance']) || (int) $row['relevance'] === 0
        ));
    }

    /**
     * Apply distance scope, annotate rows with distance_km, and sort nearest first.
     *
     * @param array<int,array<string,mixed>> $rows
     * @param array{scope:string,km:?int,town_radius_km:int} $filter
     * @return array<int,array<string,mixed>>
     */
    public static function applyDistanceFilter(
        array $rows,
        ?float $originLat,
        ?float $originLng,
        array $filter,
        ?int $townId = null,
        string $latKey = 'town_lat',
        string $lngKey = 'town_lng',
    ): array {
        if ($filter['scope'] === self::SCOPE_TOWN && $townId !== null) {
            $rows = self::filterDirectTownProviders($rows);
        }

        $maxKm = $filter['scope'] === 'km' ? $filter['km'] : null;

        return self::applyDistance($rows, $originLat, $originLng, $maxKm, $latKey, $lngKey);
    }

    public static function parseMaxDistance(mixed $raw): ?int
    {
        if ($raw === null || $raw === '' || $raw === '0') {
            return null;
        }
        $km = (int) $raw;

        return in_array($km, self::DISTANCE_OPTIONS, true) ? $km : null;
    }

    /**
     * Annotate rows with distance_km, optionally filter by max km, then sort nearest first.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function applyDistance(
        array $rows,
        ?float $originLat,
        ?float $originLng,
        ?int $maxKm = null,
        string $latKey = 'town_lat',
        string $lngKey = 'town_lng',
    ): array {
        if ($originLat === null || $originLng === null) {
            return $rows;
        }

        $out = [];
        foreach ($rows as $row) {
            $distance = self::distanceKm($originLat, $originLng, $row[$latKey] ?? null, $row[$lngKey] ?? null);
            $row['distance_km'] = $distance;
            if ($maxKm !== null) {
                if ($distance === null || $distance > $maxKm) {
                    continue;
                }
            }
            $out[] = $row;
        }

        usort($out, static function (array $a, array $b): int {
            $da = $a['distance_km'] ?? null;
            $db = $b['distance_km'] ?? null;
            if ($da === null && $db === null) {
                return 0;
            }
            if ($da === null) {
                return 1;
            }
            if ($db === null) {
                return -1;
            }
            if ($da !== $db) {
                return $da <=> $db;
            }
            $feat = ((int) ($b['is_featured'] ?? 0)) <=> ((int) ($a['is_featured'] ?? 0));
            if ($feat !== 0) {
                return $feat;
            }

            return strcmp((string) $a['business_name'], (string) $b['business_name']);
        });

        return $out;
    }
}
