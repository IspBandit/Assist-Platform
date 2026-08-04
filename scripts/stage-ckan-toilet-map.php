<?php

declare(strict_types=1);

/**
 * Non-production capped CKAN Fetch for National Public Toilet Map (DATA-012 D2).
 *
 * Temporarily enables catalogue rows, Fetch with settings.limit (default 50),
 * then restores is_enabled=0. Does NOT approve candidates (review-first).
 * Does NOT flip Ask / facilities / paid AI flags.
 *
 *   php scripts/stage-ckan-toilet-map.php
 *   php scripts/stage-ckan-toilet-map.php --state=QLD --limit=25000
 *   php scripts/stage-ckan-toilet-map.php --approve-nsw-near-batehaven
 *
 * Refuses production APP_ENV without --force.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Services\GovernmentDatasetService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$force = in_array('--force', $args, true);
$approveNsw = in_array('--approve-nsw-near-batehaven', $args, true);
$limit = 50;
$state = '';
foreach ($args as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(25000, (int) substr($arg, 8)));
    }
    if (str_starts_with($arg, '--state=')) {
        $candidate = strtoupper(trim(substr($arg, 8)));
        if (!in_array($candidate, ['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'], true)) {
            fwrite(STDERR, "Invalid Australian state/territory abbreviation: {$candidate}\n");
            exit(1);
        }
        $state = $candidate;
    }
}

$appEnv = strtolower(trim((string) Env::get('APP_ENV', 'production')));
if (!in_array($appEnv, ['local', 'development', 'dev', 'testing', 'test', 'staging'], true) && !$force) {
    fwrite(STDERR, "Refusing CKAN stage when APP_ENV={$appEnv}. Use non-prod or --force.\n");
    exit(1);
}

$brandId = (int) Database::scalar("SELECT id FROM brands WHERE brand_key = 'vanassist' LIMIT 1");
if ($brandId < 1) {
    fwrite(STDERR, "vanassist brand missing\n");
    exit(1);
}

$service = new GovernmentDatasetService();
$keys = [
    'au_national_public_toilet_map',
    'au_national_toilet_map_dump_points',
    'au_national_toilet_map_drinking_water',
    'au_national_toilet_map_showers',
];
$report = [
    'script' => 'stage-ckan-toilet-map',
    'env' => $appEnv,
    'limit' => $limit,
    'state' => $state !== '' ? $state : 'AU',
    'approve_nsw_near_batehaven' => $approveNsw,
    'fetches' => [],
    'approved' => 0,
    'flags_untouched' => true,
];

$batehavenLat = -35.7325;
$batehavenLng = 150.1985;
$radiusKm = 80;

foreach ($keys as $datasetKey) {
    $row = Database::selectOne(
        'SELECT id, is_enabled, settings_json FROM government_datasets WHERE dataset_key = ? LIMIT 1',
        [$datasetKey]
    );
    if ($row === null) {
        $report['fetches'][] = ['dataset_key' => $datasetKey, 'error' => 'catalogue missing — apply migration 094'];
        continue;
    }
    $datasetId = (int) $row['id'];
    $wasEnabled = (int) $row['is_enabled'] === 1;

    $settings = [];
    if (!empty($row['settings_json'])) {
        $decoded = json_decode((string) $row['settings_json'], true);
        if (is_array($decoded)) {
            $settings = $decoded;
        }
    }
    $settings['limit'] = $limit;
    if ($state !== '') {
        $filters = isset($settings['filters']) && is_array($settings['filters'])
            ? $settings['filters']
            : [];
        $filters['state'] = $state;
        $settings['filters'] = $filters;
    }
    Database::affecting(
        'UPDATE government_datasets SET is_enabled = 1, settings_json = ?, updated_at = NOW() WHERE id = ?',
        [json_encode($settings, JSON_THROW_ON_ERROR), $datasetId]
    );

    try {
        $result = $service->fetchDataset($datasetId, $brandId, null);
        $report['fetches'][] = ['dataset_key' => $datasetKey, 'result' => $result];
    } catch (Throwable $e) {
        $report['fetches'][] = ['dataset_key' => $datasetKey, 'error' => $e->getMessage()];
    } finally {
        if (!$wasEnabled) {
            Database::affecting(
                'UPDATE government_datasets SET is_enabled = 0, updated_at = NOW() WHERE id = ?',
                [$datasetId]
            );
        }
    }
}

if ($approveNsw) {
    $pending = $service->pendingCandidates(300);
    foreach ($pending as $candidate) {
        $lat = isset($candidate['latitude']) ? (float) $candidate['latitude'] : null;
        $lng = isset($candidate['longitude']) ? (float) $candidate['longitude'] : null;
        $locality = strtolower((string) ($candidate['locality'] ?? ''));
        $addr = strtolower((string) ($candidate['formatted_address'] ?? ''));
        $near = false;
        if ($lat !== null && $lng !== null) {
            $dLat = ($lat - $batehavenLat) * 111.32;
            $dLng = ($lng - $batehavenLng) * 100.0;
            $near = sqrt($dLat * $dLat + $dLng * $dLng) <= $radiusKm;
        }
        $nswHint = str_contains($locality, 'batehaven')
            || str_contains($locality, 'batemans')
            || str_contains($locality, 'moruya')
            || str_contains($addr, ' nsw')
            || str_contains($addr, ',nsw');
        if ($near || $nswHint) {
            try {
                $service->reviewCandidate((int) $candidate['id'], 'approve', null, 'CKAN staging NSW/Batehaven subset');
                $report['approved']++;
            } catch (Throwable $e) {
                $report['approve_errors'][] = $e->getMessage();
            }
        }
    }
}

$out = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo $out;
$evidenceDir = BASE_PATH . '/storage/logs';
if (is_dir($evidenceDir) || @mkdir($evidenceDir, 0775, true)) {
    @file_put_contents($evidenceDir . '/CKAN_TOILET_MAP_STAGE.json', $out);
}

$ok = !array_filter($report['fetches'], static fn (array $f): bool => isset($f['error']));
exit($ok ? 0 : 1);
