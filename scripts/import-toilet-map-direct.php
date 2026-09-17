<?php
/**
 * Stream-publish National Public Toilet Map CSV into traveller_facilities.
 * Bypasses per-row candidate review for authorised GREEN archive import (DATA-012).
 *
 * Usage: php scripts/import-toilet-map-direct.php [--force]
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
ini_set('memory_limit', '512M');
set_time_limit(0);
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Platform\DataSources\FacilityTypeMapper;
use App\Services\GovernmentDatasetService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$force = in_array('--force', $argv ?? [], true);
$env = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production')));
if (!$force && !in_array($env, ['local', 'development', 'dev', 'testing', 'test', 'staging'], true)) {
    fwrite(STDERR, "Refusing without --force outside non-production APP_ENV.\n");
    exit(1);
}

$csv = BASE_PATH . '/data/sources/vanassist/australia/Toiletmap.csv';
if (!is_file($csv)) {
    fwrite(STDERR, "Missing {$csv}\n");
    exit(1);
}

$service = new GovernmentDatasetService();
$dataset = $service->findDatasetByKey('au_national_public_toilet_map');
if ($dataset === null) {
    fwrite(STDERR, "Catalogue key au_national_public_toilet_map missing.\n");
    exit(1);
}

$sourceKey = GovernmentDatasetService::catalogueSourceKey(
    (string) $dataset['dataset_key'],
    (string) $dataset['connector_key']
);
$licence = mb_substr((string) ($dataset['licence'] ?? ''), 0, 120) ?: null;
$attribution = mb_substr((string) ($dataset['attribution'] ?? ''), 0, 255) ?: null;
$sourceUrl = mb_substr((string) ($dataset['endpoint_url'] ?? $dataset['source_url'] ?? ''), 0, 1000) ?: null;
$defaultType = FacilityTypeMapper::normalise((string) ($dataset['default_facility_type'] ?? 'public_toilet'));

$existing = [];
foreach (Database::select(
    'SELECT source_record_id FROM traveller_facilities WHERE source_key = ? AND deleted_at IS NULL',
    [$sourceKey]
) as $row) {
    $existing[(string) $row['source_record_id']] = true;
}

$fh = fopen($csv, 'rb');
if ($fh === false) {
    fwrite(STDERR, "Cannot open CSV\n");
    exit(1);
}
$header = fgetcsv($fh, 0, ',', '"', '\\');
if ($header === false) {
    fclose($fh);
    exit(1);
}
if (isset($header[0])) {
    $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]) ?? (string) $header[0];
}
$header = array_map(static fn ($h) => strtolower(trim((string) $h)), $header);

$inserted = 0;
$skipped = 0;
$batch = [];
$flush = static function () use (&$batch, &$inserted, $sourceKey, $licence, $attribution, $sourceUrl): void {
    if ($batch === []) {
        return;
    }
    $placeholders = [];
    $params = [];
    foreach ($batch as $row) {
        $placeholders[] = '(?, ?, ?, ?, ?, ?, ?, ?, \'unknown\', ?, ?, ?, ?, ?, ?, \'reviewed\', \'active\', NULL, NOW(), NOW(), NOW())';
        array_push(
            $params,
            $row['facility_type'],
            $row['name'],
            $row['slug'],
            $row['latitude'],
            $row['longitude'],
            $row['formatted_address'],
            $row['locality'],
            $row['town_id'],
            $row['state_id'],
            $sourceKey,
            $row['external_id'],
            $licence,
            $attribution,
            $sourceUrl,
            70
        );
    }
    $sql = 'INSERT INTO traveller_facilities
        (facility_type, name, slug, latitude, longitude, formatted_address, locality, town_id, state_id, operating_status,
         source_key, source_record_id, source_licence, source_attribution, source_url, confidence,
         verification_status, status, brand_id, last_checked_at, created_at, updated_at)
     VALUES ' . implode(',', $placeholders);
    try {
        Database::affecting($sql, $params);
        $inserted += count($batch);
    } catch (Throwable $e) {
        // Fall back to row-at-a-time if a batch collides.
        foreach ($batch as $row) {
            try {
                Database::affecting(
                    'INSERT INTO traveller_facilities
                        (facility_type, name, slug, latitude, longitude, formatted_address, locality, town_id, state_id, operating_status,
                         source_key, source_record_id, source_licence, source_attribution, source_url, confidence,
                         verification_status, status, brand_id, last_checked_at, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, \'unknown\', ?, ?, ?, ?, ?, ?, \'reviewed\', \'active\', NULL, NOW(), NOW(), NOW())',
                    [
                        $row['facility_type'],
                        $row['name'],
                        $row['slug'],
                        $row['latitude'],
                        $row['longitude'],
                        $row['formatted_address'],
                        $row['locality'],
                        $row['town_id'],
                        $row['state_id'],
                        $sourceKey,
                        $row['external_id'],
                        $licence,
                        $attribution,
                        $sourceUrl,
                        70,
                    ]
                );
                $inserted++;
            } catch (Throwable) {
                // duplicate slug/source — skip
            }
        }
    }
    $batch = [];
};

while (($data = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
    if ($data === [null]) {
        continue;
    }
    $row = [];
    foreach ($header as $idx => $key) {
        $row[$key] = trim((string) ($data[$idx] ?? ''));
    }
    $externalId = (string) ($row['facilityid'] ?? $row['facility_id'] ?? '');
    if ($externalId === '') {
        $skipped++;
        continue;
    }
    if (isset($existing[$externalId])) {
        $skipped++;
        continue;
    }
    $name = (string) ($row['name'] ?? $row['toiletname'] ?? '');
    if ($name === '') {
        $name = 'Public toilet ' . $externalId;
    }
    $slugBase = strtolower(trim($name));
    $slugBase = preg_replace('/[^a-z0-9]+/', '-', $slugBase) ?? 'facility';
    $slugBase = trim($slugBase, '-') ?: 'facility';
    $slug = $slugBase . '-' . substr(sha1($sourceKey . '|' . $externalId), 0, 8);
    $lat = is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null;
    $lng = is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null;
    $batch[] = [
        'facility_type' => $defaultType,
        'name' => mb_substr($name, 0, 190),
        'slug' => $slug,
        'latitude' => $lat,
        'longitude' => $lng,
        'formatted_address' => mb_substr((string) ($row['address1'] ?? $row['address'] ?? ''), 0, 500) ?: null,
        'locality' => mb_substr((string) ($row['town'] ?? $row['suburb'] ?? ''), 0, 120) ?: null,
        'town_id' => null,
        'state_id' => null,
        'external_id' => mb_substr($externalId, 0, 255),
    ];
    $existing[$externalId] = true;
    if (count($batch) >= 200) {
        $flush();
        if ($inserted % 2000 === 0) {
            fwrite(STDERR, "inserted={$inserted} skipped={$skipped}\n");
        }
    }
}
$flush();
fclose($fh);

Database::affecting(
    'UPDATE government_datasets SET is_enabled=1, last_checked_at=NOW(), last_imported_at=NOW(), last_error=NULL, updated_at=NOW() WHERE id=?',
    [(int) $dataset['id']]
);

echo json_encode([
    'dataset_key' => 'au_national_public_toilet_map',
    'source_key' => $sourceKey,
    'inserted' => $inserted,
    'skipped' => $skipped,
], JSON_PRETTY_PRINT) . "\n";
