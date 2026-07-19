<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use Throwable;

/**
 * National town coverage report: how many localities have local providers
 * (base_town_id) and how many are reached via service areas / region / state.
 */
final class TownCoverageService
{
    /**
     * Aggregate coverage by state plus national totals.
     *
     * "Local" = active providers with base_town_id = that town.
     * "Serving" = local OR town/region/state service area OR provider.region_id match
     * (radius areas omitted for speed — same approximation as directory breadth).
     *
     * @return array{
     *   totals: array<string,int|float>,
     *   by_state: list<array<string,int|string>>,
     *   thin_sample: list<array<string,mixed>>
     * }
     */
    public static function report(int $thinSampleLimit = 40): array
    {
        $empty = [
            'totals' => [
                'towns' => 0,
                'local_zero' => 0,
                'local_thin' => 0,
                'local_ok' => 0,
                'serving_zero' => 0,
                'serving_covered' => 0,
                'pct_local_covered' => 0.0,
                'pct_serving_covered' => 0.0,
            ],
            'by_state' => [],
            'thin_sample' => [],
        ];

        try {
            $localCounts = self::localProviderCounts();
            $townAreas = self::townIdsWithTownArea();
            $regionAreas = self::regionIdsWithRegionArea();
            $stateAreas = self::stateIdsWithStateArea();
            $regionsWithBase = self::regionIdsWithBaseProvider();

            $towns = Database::select(
                'SELECT t.id, t.name, t.region_id, t.state_id, s.abbreviation AS state '
                . 'FROM towns t JOIN states s ON s.id = t.state_id '
                . 'WHERE t.is_active = 1 ORDER BY s.abbreviation, t.name'
            );

            $byState = [];
            $thinSample = [];

            foreach ($towns as $town) {
                $id = (int) $town['id'];
                $state = (string) $town['state'];
                $regionId = (int) ($town['region_id'] ?? 0);
                $stateId = (int) $town['state_id'];
                $local = (int) ($localCounts[$id] ?? 0);
                $serving = $local > 0
                    || isset($townAreas[$id])
                    || ($regionId > 0 && isset($regionAreas[$regionId]))
                    || ($regionId > 0 && isset($regionsWithBase[$regionId]))
                    || isset($stateAreas[$stateId]);

                if (!isset($byState[$state])) {
                    $byState[$state] = [
                        'state' => $state,
                        'towns' => 0,
                        'local_zero' => 0,
                        'local_thin' => 0,
                        'local_ok' => 0,
                        'serving_zero' => 0,
                        'serving_covered' => 0,
                    ];
                }

                $byState[$state]['towns']++;
                if ($local === 0) {
                    $byState[$state]['local_zero']++;
                } elseif ($local <= 2) {
                    $byState[$state]['local_thin']++;
                } else {
                    $byState[$state]['local_ok']++;
                }
                if ($serving) {
                    $byState[$state]['serving_covered']++;
                } else {
                    $byState[$state]['serving_zero']++;
                }

                if ($local <= 2) {
                    $thinSample[] = [
                        'id' => $id,
                        'name' => (string) $town['name'],
                        'state' => $state,
                        'local_count' => $local,
                        'serving' => $serving,
                    ];
                }
            }

            usort($thinSample, static function (array $a, array $b): int {
                if ($a['local_count'] !== $b['local_count']) {
                    return $a['local_count'] <=> $b['local_count'];
                }
                if ($a['serving'] !== $b['serving']) {
                    return $a['serving'] ? 1 : -1;
                }

                return strcmp($a['name'], $b['name']);
            });
            $thinSample = array_slice($thinSample, 0, $thinSampleLimit);

            ksort($byState);
            $rows = array_values($byState);

            $totals = [
                'towns' => 0,
                'local_zero' => 0,
                'local_thin' => 0,
                'local_ok' => 0,
                'serving_zero' => 0,
                'serving_covered' => 0,
                'pct_local_covered' => 0.0,
                'pct_serving_covered' => 0.0,
            ];
            foreach ($rows as $row) {
                $totals['towns'] += $row['towns'];
                $totals['local_zero'] += $row['local_zero'];
                $totals['local_thin'] += $row['local_thin'];
                $totals['local_ok'] += $row['local_ok'];
                $totals['serving_zero'] += $row['serving_zero'];
                $totals['serving_covered'] += $row['serving_covered'];
            }
            if ($totals['towns'] > 0) {
                $localCovered = $totals['towns'] - $totals['local_zero'];
                $totals['pct_local_covered'] = round(100 * $localCovered / $totals['towns'], 1);
                $totals['pct_serving_covered'] = round(100 * $totals['serving_covered'] / $totals['towns'], 1);
            }

            return [
                'totals' => $totals,
                'by_state' => $rows,
                'thin_sample' => $thinSample,
            ];
        } catch (Throwable) {
            return $empty;
        }
    }

    /**
     * Towns with few/no local providers — queue for Places / ABR gap fills.
     *
     * @return list<array{id:int,name:string,state:string,local_count:int,serving:bool}>
     */
    public static function thinTowns(int $maxLocal = 2, int $limit = 500): array
    {
        try {
            $localCounts = self::localProviderCounts();
            $townAreas = self::townIdsWithTownArea();
            $regionAreas = self::regionIdsWithRegionArea();
            $stateAreas = self::stateIdsWithStateArea();
            $regionsWithBase = self::regionIdsWithBaseProvider();

            $towns = Database::select(
                'SELECT t.id, t.name, t.region_id, t.state_id, s.abbreviation AS state '
                . 'FROM towns t JOIN states s ON s.id = t.state_id '
                . 'WHERE t.is_active = 1 ORDER BY s.abbreviation, t.name'
            );

            $out = [];
            foreach ($towns as $town) {
                $id = (int) $town['id'];
                $local = (int) ($localCounts[$id] ?? 0);
                if ($local > $maxLocal) {
                    continue;
                }
                $regionId = (int) ($town['region_id'] ?? 0);
                $stateId = (int) $town['state_id'];
                $serving = $local > 0
                    || isset($townAreas[$id])
                    || ($regionId > 0 && isset($regionAreas[$regionId]))
                    || ($regionId > 0 && isset($regionsWithBase[$regionId]))
                    || isset($stateAreas[$stateId]);

                $out[] = [
                    'id' => $id,
                    'name' => (string) $town['name'],
                    'state' => (string) $town['state'],
                    'local_count' => $local,
                    'serving' => $serving,
                ];
            }

            usort($out, static function (array $a, array $b): int {
                if ($a['local_count'] !== $b['local_count']) {
                    return $a['local_count'] <=> $b['local_count'];
                }
                if ($a['serving'] !== $b['serving']) {
                    return $a['serving'] ? 1 : -1;
                }

                return strcmp($a['name'], $b['name']);
            });

            return array_slice($out, 0, $limit);
        } catch (Throwable) {
            return [];
        }
    }

    /** @return array<int,int> town_id => local provider count */
    private static function localProviderCounts(): array
    {
        $rows = Database::select(
            'SELECT base_town_id AS town_id, COUNT(*) AS cnt '
            . 'FROM providers '
            . 'WHERE status = \'active\' AND deleted_at IS NULL AND base_town_id IS NOT NULL '
            . 'GROUP BY base_town_id'
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['town_id']] = (int) $row['cnt'];
        }

        return $map;
    }

    /** @return array<int,true> */
    private static function townIdsWithTownArea(): array
    {
        $rows = Database::select(
            "SELECT DISTINCT psa.town_id FROM provider_service_areas psa "
            . 'JOIN providers p ON p.id = psa.provider_id '
            . "WHERE psa.area_type = 'town' AND psa.town_id IS NOT NULL "
            . "AND p.status = 'active' AND p.deleted_at IS NULL"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['town_id']] = true;
        }

        return $map;
    }

    /** @return array<int,true> */
    private static function regionIdsWithRegionArea(): array
    {
        $rows = Database::select(
            "SELECT DISTINCT psa.region_id FROM provider_service_areas psa "
            . 'JOIN providers p ON p.id = psa.provider_id '
            . "WHERE psa.area_type = 'region' AND psa.region_id IS NOT NULL "
            . "AND p.status = 'active' AND p.deleted_at IS NULL"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['region_id']] = true;
        }

        return $map;
    }

    /** @return array<int,true> */
    private static function stateIdsWithStateArea(): array
    {
        $rows = Database::select(
            "SELECT DISTINCT psa.state_id FROM provider_service_areas psa "
            . 'JOIN providers p ON p.id = psa.provider_id '
            . "WHERE psa.area_type = 'state' AND psa.state_id IS NOT NULL "
            . "AND p.status = 'active' AND p.deleted_at IS NULL"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['state_id']] = true;
        }

        return $map;
    }

    /** @return array<int,true> regions that have at least one active provider based there */
    private static function regionIdsWithBaseProvider(): array
    {
        $rows = Database::select(
            'SELECT DISTINCT region_id FROM providers '
            . "WHERE status = 'active' AND deleted_at IS NULL AND region_id IS NOT NULL"
        );
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row['region_id']] = true;
        }

        return $map;
    }
}
