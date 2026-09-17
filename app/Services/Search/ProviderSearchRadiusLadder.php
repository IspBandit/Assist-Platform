<?php

declare(strict_types=1);

namespace App\Services\Search;

/**
 * Regional specialist searches expand outward until enough providers appear.
 * Explicit user distance choices are never widened.
 *
 * Backlog: VAN-011 / DATA-013.
 */
final class ProviderSearchRadiusLadder
{
    /**
     * @return list<int>
     */
    public static function stepsKm(): array
    {
        $raw = config('geo.provider_search_radius_ladder_km', [25, 75, 150, 300]);
        if (!is_array($raw) || $raw === []) {
            return [25, 75, 150, 300];
        }
        $steps = [];
        foreach ($raw as $value) {
            $km = (int) $value;
            if ($km >= 1 && $km <= 500) {
                $steps[] = $km;
            }
        }
        $steps = array_values(array_unique($steps));
        sort($steps);

        return $steps !== [] ? $steps : [25, 75, 150, 300];
    }

    public static function minResults(): int
    {
        return max(1, min(20, (int) config('geo.provider_search_min_results', 3)));
    }

    /**
     * Walk the ladder calling $searchAtKm until enough rows or the last step.
     *
     * @param callable(int):list<array<string,mixed>> $searchAtKm
     * @return array{
     *   rows:list<array<string,mixed>>,
     *   radius_km:int,
     *   expanded:bool,
     *   started_km:int,
     *   message:?string
     * }
     */
    public static function expand(callable $searchAtKm, ?int $explicitKm = null): array
    {
        if ($explicitKm !== null) {
            $km = max(1, min(500, $explicitKm));
            $rows = array_values($searchAtKm($km));

            return [
                'rows' => $rows,
                'radius_km' => $km,
                'expanded' => false,
                'started_km' => $km,
                'message' => null,
            ];
        }

        $steps = self::stepsKm();
        $min = self::minResults();
        $started = $steps[0];
        $bestRows = [];
        $usedKm = $started;

        foreach ($steps as $km) {
            $rows = array_values($searchAtKm($km));
            $bestRows = $rows;
            $usedKm = $km;
            if (count($rows) >= $min) {
                break;
            }
        }

        $expanded = $usedKm > $started;
        $message = null;
        if ($expanded && $bestRows !== []) {
            $message = 'No matching provider was found within ' . $started
                . ' km. Showing the nearest matching providers within ' . $usedKm . ' km.';
        }

        return [
            'rows' => $bestRows,
            'radius_km' => $usedKm,
            'expanded' => $expanded,
            'started_km' => $started,
            'message' => $message,
        ];
    }
}
