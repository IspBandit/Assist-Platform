<?php

declare(strict_types=1);

namespace App\Services\RoadDistance;

final class RoadDistanceService
{
    public function __construct(private readonly ?RouteMatrixClient $client = null) {}

    /**
     * Replace preliminary straight-line measurements with Google road distance.
     * Coordinate duplicates are billed once per request. Google route results
     * are deliberately not persisted because Maps Platform storage is restricted.
     *
     * @param array<string,list<array<string,mixed>>> $groups
     * @return array<string,list<array<string,mixed>>>
     */
    public function enrichGroups(
        array $groups,
        ?float $originLatitude,
        ?float $originLongitude,
        ?int $radiusKm = null,
    ): array {
        $client = $this->client ?? new GoogleRoutesMatrixClient();
        if ($originLatitude === null || $originLongitude === null || !$client->enabled()) {
            return $groups;
        }

        $coordinates = [];
        $coordinateIndex = [];
        $rowCoordinates = [];
        foreach ($groups as $group => $rows) {
            foreach ($rows as $rowIndex => $row) {
                $pair = $this->coordinatePair($row);
                if ($pair === null) {
                    continue;
                }
                $key = number_format($pair['latitude'], 6, '.', '') . ',' . number_format($pair['longitude'], 6, '.', '');
                if (!isset($coordinateIndex[$key])) {
                    if (count($coordinates) >= $client->maxDestinations()) {
                        continue;
                    }
                    $coordinateIndex[$key] = count($coordinates);
                    $coordinates[] = $pair;
                }
                $rowCoordinates[$group][$rowIndex] = $coordinateIndex[$key];
            }
        }
        if ($coordinates === []) {
            return $groups;
        }

        try {
            $routes = $client->routes($originLatitude, $originLongitude, $coordinates);
        } catch (\Throwable) {
            // Search remains available during a temporary routing outage. The
            // existing values remain explicitly labelled straight-line in UI.
            return $groups;
        }
        if ($routes === []) {
            return $groups;
        }

        $result = [];
        foreach ($groups as $group => $rows) {
            $result[$group] = [];
            foreach ($rows as $rowIndex => $row) {
                $routeIndex = $rowCoordinates[$group][$rowIndex] ?? null;
                $route = $routeIndex !== null ? ($routes[$routeIndex] ?? null) : null;
                if ($route === null) {
                    // Once routing succeeded, omit unmeasured overflow/unroutable
                    // cards so every displayed distance and radius decision is real.
                    continue;
                }
                $roadKm = $route['distance_meters'] / 1000;
                if ($radiusKm !== null && $roadKm > $radiusKm) {
                    continue;
                }
                $row['straight_line_distance_km'] = $row['distance_km'] ?? null;
                $row['distance_km'] = round($roadKm, 1);
                $row['distance_metric'] = 'road';
                $row['drive_time_seconds'] = $route['duration_seconds'];
                $row['distance_attribution'] = 'Google Maps';
                $result[$group][] = $row;
            }
            usort($result[$group], static function (array $a, array $b): int {
                $distance = ((float) ($a['distance_km'] ?? INF)) <=> ((float) ($b['distance_km'] ?? INF));
                return $distance !== 0 ? $distance : strcmp(
                    (string) ($a['business_name'] ?? $a['name'] ?? ''),
                    (string) ($b['business_name'] ?? $b['name'] ?? '')
                );
            });
        }

        return $result;
    }

    /** @param array<string,mixed> $row */
    public static function displayLabel(array $row): string
    {
        if (!isset($row['distance_km']) || !is_numeric($row['distance_km'])) {
            return '';
        }
        $kilometres = max(0.1, (float) $row['distance_km']);
        if (($row['distance_metric'] ?? '') !== 'road') {
            return number_format($kilometres, $kilometres < 10 ? 1 : 0) . ' km straight-line';
        }
        $label = number_format($kilometres, $kilometres < 10 ? 1 : 0) . ' km by road';
        $seconds = is_numeric($row['drive_time_seconds'] ?? null) ? (int) $row['drive_time_seconds'] : 0;
        if ($seconds > 0) {
            $minutes = max(1, (int) round($seconds / 60));
            $label .= ' · about ' . ($minutes >= 60
                ? intdiv($minutes, 60) . ' hr' . (intdiv($minutes, 60) === 1 ? '' : 's')
                    . ($minutes % 60 > 0 ? ' ' . ($minutes % 60) . ' min' : '')
                : $minutes . ' min');
        }

        return $label;
    }

    /** @param array<string,list<array<string,mixed>>> $groups */
    public static function groupsUseRoadDistance(array $groups): bool
    {
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                if (($row['distance_metric'] ?? '') === 'road') {
                    return true;
                }
            }
        }

        return false;
    }

    /** @param array<string,mixed> $row @return array{latitude:float,longitude:float}|null */
    private function coordinatePair(array $row): ?array
    {
        foreach ([['latitude', 'longitude'], ['town_lat', 'town_lng']] as [$latKey, $lngKey]) {
            if (!is_numeric($row[$latKey] ?? null) || !is_numeric($row[$lngKey] ?? null)) {
                continue;
            }
            $latitude = (float) $row[$latKey];
            $longitude = (float) $row[$lngKey];
            if ($latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180) {
                return ['latitude' => $latitude, 'longitude' => $longitude];
            }
        }

        return null;
    }
}
