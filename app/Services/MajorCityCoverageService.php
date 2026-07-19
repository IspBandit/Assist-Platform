<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Tracks provider coverage in major Australian cities and promotes key towns.
 */
final class MajorCityCoverageService
{
    /** @return array{metros:array<int,array<string,mixed>>,cities:array<int,array<string,mixed>>} */
    public static function seedData(): array
    {
        $file = base_path('database/seeds/major_cities.json');
        if (!is_file($file)) {
            return ['metros' => [], 'cities' => []];
        }
        $data = json_decode((string) file_get_contents($file), true);

        return [
            'metros' => is_array($data['metros'] ?? null) ? $data['metros'] : [],
            'cities' => is_array($data['cities'] ?? null) ? $data['cities'] : [],
        ];
    }

    /**
     * Provider counts serving each major city (direct town coverage or base town match).
     *
     * @return array<int,array<string,mixed>>
     */
    public static function coverageReport(): array
    {
        $rows = [];
        foreach (self::allCities() as $city) {
            $town = Database::selectOne(
                'SELECT t.id, t.name, t.is_featured, t.noindex, s.abbreviation AS state_abbr '
                . 'FROM towns t JOIN states s ON s.id = t.state_id '
                . 'WHERE t.is_active = 1 AND s.abbreviation = ? AND t.name = ? LIMIT 1',
                [$city['state'], $city['name']]
            );

            $townId = $town !== null ? (int) $town['id'] : 0;
            $providerCount = 0;
            if ($townId > 0) {
                $sql = ProviderCoverage::sqlServesTown();
                $params = ProviderCoverage::servesTownParams($townId);
                $providerCount = (int) Database::scalar(
                    'SELECT COUNT(DISTINCT p.id) FROM providers p '
                    . 'WHERE p.status = \'active\' AND p.deleted_at IS NULL AND ' . $sql,
                    $params
                );
            }

            $rows[] = [
                'name'           => (string) $city['name'],
                'state'          => (string) $city['state'],
                'kind'           => (string) ($city['kind'] ?? 'city'),
                'town_id'        => $townId ?: null,
                'provider_count' => $providerCount,
                'is_featured'    => !empty($town['is_featured']),
                'target'         => (int) ($city['target_providers'] ?? 5),
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            if ($a['provider_count'] !== $b['provider_count']) {
                return $a['provider_count'] <=> $b['provider_count'];
            }

            return strcmp($a['name'], $b['name']);
        });

        return $rows;
    }

    /** Mark major-city town rows as featured so they appear in filters and sitemaps. */
    public static function featureMajorCityTowns(): int
    {
        $updated = 0;
        foreach (self::allCities() as $city) {
            $stmt = Database::query(
                'UPDATE towns t JOIN states s ON s.id = t.state_id '
                . 'SET t.is_featured = 1, t.noindex = 0, t.updated_at = NOW() '
                . 'WHERE t.is_active = 1 AND s.abbreviation = ? AND t.name = ?',
                [$city['state'], $city['name']]
            );
            $updated += $stmt->rowCount();
        }

        return $updated;
    }

    /** @return array<int,array<string,mixed>> */
    private static function allCities(): array
    {
        $data = self::seedData();
        $out = [];
        foreach ($data['metros'] as $row) {
            $row['kind'] = 'metro';
            $row['target_providers'] = 15;
            $out[] = $row;
        }
        foreach ($data['cities'] as $row) {
            $row['kind'] = 'city';
            $row['target_providers'] = 5;
            $out[] = $row;
        }

        return $out;
    }
}
