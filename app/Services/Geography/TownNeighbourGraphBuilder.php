<?php

declare(strict_types=1);

namespace App\Services\Geography;

use App\Core\Database;
use App\Helpers\Geo;
use App\Services\Settings;
use RuntimeException;
use Throwable;

/**
 * Rebuilds town_neighbours from measurable town coordinates (VAN-011).
 *
 * Same-state, within neighbour_max_km, up to neighbour_limit nearest towns,
 * with bidirectional edges. Idempotent via a settings fingerprint so migrate
 * and ops can re-run safely after coordinate corrections.
 */
final class TownNeighbourGraphBuilder
{
    public const ALGORITHM_VERSION = 'v1';
    public const SETTING_FINGERPRINT = 'town_neighbour_graph_fingerprint';

    private const INSERT_CHUNK = 500;

    /**
     * Pure edge computation for tests and dry-run inspection.
     *
     * @param list<array{id:int,state_id:int,latitude:float,longitude:float}> $towns
     * @return list<array{town_id:int,neighbour_town_id:int,distance_km:float}>
     */
    public static function edgesFromTowns(array $towns, ?int $maxKm = null, ?int $limit = null): array
    {
        $maxKm = max(1, $maxKm ?? (int) config('geo.neighbour_max_km', 50));
        $limit = max(1, $limit ?? (int) config('geo.neighbour_limit', 8));
        $byState = [];
        foreach ($towns as $town) {
            $id = (int) ($town['id'] ?? 0);
            $stateId = (int) ($town['state_id'] ?? 0);
            $lat = $town['latitude'] ?? null;
            $lng = $town['longitude'] ?? null;
            if ($id < 1 || $stateId < 1 || !is_numeric($lat) || !is_numeric($lng)) {
                continue;
            }
            $byState[$stateId][] = [
                'id' => $id,
                'latitude' => (float) $lat,
                'longitude' => (float) $lng,
            ];
        }

        $directed = [];
        $latDelta = $maxKm / 111.32;
        foreach ($byState as $group) {
            $count = count($group);
            for ($i = 0; $i < $count; $i++) {
                $origin = $group[$i];
                $candidates = [];
                for ($j = 0; $j < $count; $j++) {
                    if ($i === $j) {
                        continue;
                    }
                    $other = $group[$j];
                    if (abs($other['latitude'] - $origin['latitude']) > $latDelta) {
                        continue;
                    }
                    $distance = Geo::haversineExactKm(
                        $origin['latitude'],
                        $origin['longitude'],
                        $other['latitude'],
                        $other['longitude']
                    );
                    if ($distance > $maxKm) {
                        continue;
                    }
                    $candidates[] = [
                        'neighbour_town_id' => $other['id'],
                        'distance_km' => round($distance, 2),
                    ];
                }
                usort(
                    $candidates,
                    static fn (array $a, array $b): int => $a['distance_km'] <=> $b['distance_km']
                        ?: $a['neighbour_town_id'] <=> $b['neighbour_town_id']
                );
                foreach (array_slice($candidates, 0, $limit) as $candidate) {
                    $directed[$origin['id'] . ':' . $candidate['neighbour_town_id']] = [
                        'town_id' => $origin['id'],
                        'neighbour_town_id' => $candidate['neighbour_town_id'],
                        'distance_km' => (float) $candidate['distance_km'],
                    ];
                }
            }
        }

        $edges = [];
        foreach ($directed as $edge) {
            $key = $edge['town_id'] . ':' . $edge['neighbour_town_id'];
            $edges[$key] = $edge;
            $reverseKey = $edge['neighbour_town_id'] . ':' . $edge['town_id'];
            if (!isset($edges[$reverseKey])) {
                $edges[$reverseKey] = [
                    'town_id' => $edge['neighbour_town_id'],
                    'neighbour_town_id' => $edge['town_id'],
                    'distance_km' => $edge['distance_km'],
                ];
            }
        }

        $list = array_values($edges);
        usort(
            $list,
            static fn (array $a, array $b): int => $a['town_id'] <=> $b['town_id']
                ?: $a['distance_km'] <=> $b['distance_km']
                ?: $a['neighbour_town_id'] <=> $b['neighbour_town_id']
        );

        return $list;
    }

    /** @return array<string,mixed> */
    public static function afterMigrations(): array
    {
        return self::rebuild(false, false);
    }

    /**
     * @return array{
     *   skipped?:bool,
     *   note?:string,
     *   dry_run?:bool,
     *   towns?:int,
     *   edges?:int,
     *   inserted?:int,
     *   fingerprint?:string
     * }
     */
    public static function rebuild(bool $dryRun = false, bool $force = false): array
    {
        if (!Database::tableExists('towns') || !Database::tableExists('town_neighbours')) {
            return ['skipped' => true, 'note' => 'town neighbour prerequisites are unavailable'];
        }

        $maxKm = max(1, (int) config('geo.neighbour_max_km', 50));
        $limit = max(1, (int) config('geo.neighbour_limit', 8));
        $fingerprint = self::fingerprint($maxKm, $limit);
        $existing = (int) Database::scalar('SELECT COUNT(*) FROM town_neighbours');
        if (!$force && !$dryRun && $existing > 0 && Settings::get(self::SETTING_FINGERPRINT, '') === $fingerprint) {
            return [
                'skipped' => true,
                'note' => 'town neighbour graph is current',
                'edges' => $existing,
                'fingerprint' => $fingerprint,
            ];
        }

        $towns = Database::select(
            'SELECT id, state_id, latitude, longitude FROM towns '
            . 'WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL '
            . 'ORDER BY state_id ASC, id ASC'
        );
        /** @var list<array{id:int,state_id:int,latitude:float,longitude:float}> $normalised */
        $normalised = [];
        foreach ($towns as $town) {
            $normalised[] = [
                'id' => (int) $town['id'],
                'state_id' => (int) $town['state_id'],
                'latitude' => (float) $town['latitude'],
                'longitude' => (float) $town['longitude'],
            ];
        }
        $edges = self::edgesFromTowns($normalised, $maxKm, $limit);

        if ($dryRun) {
            return [
                'dry_run' => true,
                'towns' => count($normalised),
                'edges' => count($edges),
                'fingerprint' => $fingerprint,
            ];
        }

        Database::beginTransaction();
        try {
            Database::query('DELETE FROM town_neighbours');
            $inserted = 0;
            foreach (array_chunk($edges, self::INSERT_CHUNK) as $chunk) {
                $placeholders = [];
                $params = [];
                foreach ($chunk as $edge) {
                    $placeholders[] = '(?,?,?)';
                    array_push(
                        $params,
                        $edge['town_id'],
                        $edge['neighbour_town_id'],
                        $edge['distance_km']
                    );
                }
                Database::query(
                    'INSERT INTO town_neighbours (town_id, neighbour_town_id, distance_km) VALUES '
                    . implode(',', $placeholders),
                    $params
                );
                $inserted += count($chunk);
            }
            if (Database::tableExists('site_settings')) {
                Settings::set(self::SETTING_FINGERPRINT, $fingerprint);
            }
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw new RuntimeException('Town neighbour graph rebuild failed: ' . $e->getMessage(), 0, $e);
        }

        return [
            'towns' => count($normalised),
            'edges' => count($edges),
            'inserted' => $inserted,
            'fingerprint' => $fingerprint,
        ];
    }

    private static function fingerprint(int $maxKm, int $limit): string
    {
        $coordFp = '';
        if (Database::tableExists('site_settings')) {
            $coordFp = (string) Settings::get('town_coordinate_pack_fingerprint', '');
        }
        $townSignal = (string) Database::scalar(
            'SELECT COUNT(*) FROM towns WHERE is_active = 1 AND latitude IS NOT NULL AND longitude IS NOT NULL'
        );

        return hash(
            'sha256',
            self::ALGORITHM_VERSION . '|' . $maxKm . '|' . $limit . '|' . $coordFp . '|' . $townSignal
        );
    }
}
