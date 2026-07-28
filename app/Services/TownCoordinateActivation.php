<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Activates reviewed national town coordinates from the versioned seed pack. */
final class TownCoordinateActivation
{
    private const SETTING_FINGERPRINT = 'town_coordinate_pack_fingerprint';
    private const CHUNK = 300;

    /** @return array<string,mixed> */
    public static function afterMigrations(): array
    {
        $path = base_path('database/seeds/towns_national.json');
        if (!Database::tableExists('towns') || !Database::tableExists('site_settings') || !is_file($path)) {
            return ['skipped' => true, 'note' => 'town coordinate prerequisites are unavailable'];
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM towns') < 1000) {
            return ['skipped' => true, 'note' => 'national towns are not seeded'];
        }

        $fingerprint = hash_file('sha256', $path) ?: '';
        if ($fingerprint !== '' && Settings::get(self::SETTING_FINGERPRINT, '') === $fingerprint) {
            return ['skipped' => true, 'note' => 'town coordinate pack is current'];
        }

        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $regionSlugs = self::regionSlugs();
        Database::query('DROP TEMPORARY TABLE IF EXISTS tmp_town_coordinate_sync');
        Database::query(
            "CREATE TEMPORARY TABLE tmp_town_coordinate_sync ("
            . "state_abbr VARCHAR(10) NOT NULL, town_slug VARCHAR(150) NOT NULL, region_key VARCHAR(80) NULL, "
            . "latitude DECIMAL(10,7) NULL, longitude DECIMAL(10,7) NULL, coordinate_source VARCHAR(80) NULL, "
            . "coordinate_confidence ENUM('authoritative','statistical','unverified') NOT NULL, coordinate_reference VARCHAR(100) NULL, "
            . "PRIMARY KEY (state_abbr,town_slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $rowsByKey = [];
        foreach ((array) ($data['towns'] ?? []) as $town) {
            $row = [
                (string) ($town['state'] ?? ''), str_slug((string) ($town['name'] ?? '')),
                $regionSlugs[(string) ($town['region'] ?? '')] ?? (string) ($town['region'] ?? ''),
                $town['lat'] ?? null, $town['lng'] ?? null,
                (string) ($town['coordinate_source'] ?? 'australian-postcodes'),
                in_array(($town['coordinate_confidence'] ?? ''), ['authoritative', 'statistical'], true)
                    ? (string) $town['coordinate_confidence'] : 'unverified',
                isset($town['coordinate_reference']) ? (string) $town['coordinate_reference'] : null,
            ];

            // Punctuation variants such as O'Connor and O’Connor share one
            // database slug. Collapse them before chunking, retaining the
            // strongest available coordinate provenance.
            $key = $row[0] . '|' . $row[1];
            if (!isset($rowsByKey[$key]) || self::confidenceRank((string) $row[6]) > self::confidenceRank((string) $rowsByKey[$key][6])) {
                $rowsByKey[$key] = $row;
            }
        }
        foreach (array_chunk(array_values($rowsByKey), self::CHUNK) as $rows) {
            self::insertChunk($rows);
        }

        $updated = Database::affecting(
            'UPDATE towns t JOIN states s ON s.id=t.state_id '
            . 'JOIN tmp_town_coordinate_sync x ON x.state_abbr=s.abbreviation AND x.town_slug=t.slug '
            . 'LEFT JOIN regions r ON r.state_id=s.id AND r.slug=x.region_key '
            . 'SET t.latitude=x.latitude,t.longitude=x.longitude,t.region_id=COALESCE(r.id,t.region_id),'
            . 't.coordinate_source=x.coordinate_source,t.coordinate_confidence=x.coordinate_confidence,'
            . 't.coordinate_reference=x.coordinate_reference,t.coordinate_verified_at=CASE '
            . "WHEN x.coordinate_confidence IN ('authoritative','statistical') THEN CURRENT_DATE ELSE NULL END,t.updated_at=NOW()"
        );
        Settings::set(self::SETTING_FINGERPRINT, $fingerprint);
        Database::query('DROP TEMPORARY TABLE IF EXISTS tmp_town_coordinate_sync');

        return ['updated' => $updated, 'fingerprint' => $fingerprint];
    }

    /** @param array<int,array<int,mixed>> $rows */
    private static function insertChunk(array $rows): void
    {
        $placeholders = [];
        $params = [];
        foreach ($rows as $row) {
            $placeholders[] = '(?,?,?,?,?,?,?,?)';
            array_push($params, ...$row);
        }
        Database::query(
            'INSERT INTO tmp_town_coordinate_sync '
            . '(state_abbr,town_slug,region_key,latitude,longitude,coordinate_source,coordinate_confidence,coordinate_reference) VALUES '
            . implode(',', $placeholders),
            $params
        );
    }

    private static function confidenceRank(string $confidence): int
    {
        return match ($confidence) {
            'authoritative' => 3,
            'statistical' => 2,
            default => 1,
        };
    }

    /** @return array<string,string> canonical region key => database slug */
    private static function regionSlugs(): array
    {
        $path = base_path('database/seeds/national_import.json');
        $data = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        $map = [];
        foreach ((array) ($data['regions'] ?? []) as $region) {
            $map[(string) ($region['id'] ?? '')] = str_slug((string) ($region['label'] ?? ''));
        }
        return $map;
    }
}
