<?php
/**
 * Stage archived GREEN government datasets into traveller_facility_import_*
 * using existing GovernmentDatasetService connectors (DATA-012).
 *
 * Usage:
 *   php scripts/import-archived-green-facilities.php --force --approve
 *   php scripts/import-archived-green-facilities.php --dataset=qld_roadside_amenities --force --approve
 *
 * National Toilet Map (~25k rows): prefer scripts/import-toilet-map-direct.php
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
ini_set('memory_limit', '1024M');
set_time_limit(0);
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Services\GovernmentDatasetService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$argvList = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$approve = in_array('--approve', $argvList, true);
$force = in_array('--force', $argvList, true);
$only = null;
foreach ($argvList as $arg) {
    if (str_starts_with($arg, '--dataset=')) {
        $only = substr($arg, 10);
    }
}

$env = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production')));
if (!$force && !in_array($env, ['local', 'development', 'dev', 'testing', 'test', 'staging'], true)) {
    fwrite(STDERR, "Refusing to run outside non-production APP_ENV without --force (APP_ENV={$env}).\n");
    exit(1);
}

/** @var list<array{dataset_key:string,path:string,format:string,name_field?:string,id_field?:string}> $packs */
$packs = [
    ['dataset_key' => 'au_national_public_toilet_map', 'path' => 'data/sources/vanassist/australia/Toiletmap.csv', 'format' => 'csv', 'name_field' => 'Name', 'id_field' => 'FacilityID'],
    ['dataset_key' => 'qld_roadside_amenities', 'path' => 'data/sources/vanassist/qld/qld_roadside_amenities.geojson', 'format' => 'geojson', 'name_field' => 'name', 'id_field' => 'objectid'],
    ['dataset_key' => 'qld_operational_boat_facilities', 'path' => 'data/sources/vanassist/qld/qld_operational_boat_facilities.geojson', 'format' => 'geojson', 'name_field' => 'name', 'id_field' => 'objectid'],
    ['dataset_key' => 'nsw_rest_areas', 'path' => 'data/sources/vanassist/nsw/rest-areas-csv-format.csv', 'format' => 'csv', 'name_field' => 'rest_area_name', 'id_field' => 'cartodb_id'],
    ['dataset_key' => 'nsw_boat_ramps', 'path' => 'data/sources/vanassist/nsw/nsw_boat_ramps.geojson', 'format' => 'geojson', 'name_field' => 'DESCRIPTION', 'id_field' => 'BOAT_RAMP_ID'],
    ['dataset_key' => 'vic_recreation_sites', 'path' => 'data/sources/vanassist/vic/recreation_sites.geojson', 'format' => 'geojson', 'name_field' => 'recweb_name', 'id_field' => 'objectid'],
    ['dataset_key' => 'wa_heavy_vehicle_rest_areas', 'path' => 'data/sources/vanassist/wa/heavy_vehicle_rest_area.geojson', 'format' => 'geojson', 'name_field' => 'COMMON_USAGE_NAME', 'id_field' => 'OBJECTID'],
    ['dataset_key' => 'wa_major_rest_areas', 'path' => 'data/sources/vanassist/wa/major_rest_areas.geojson', 'format' => 'geojson', 'name_field' => 'COMMON_USAGE_NAME', 'id_field' => 'OBJECTID'],
    ['dataset_key' => 'wa_minor_rest_areas', 'path' => 'data/sources/vanassist/wa/minor_rest_areas.geojson', 'format' => 'geojson', 'name_field' => 'COMMON_USAGE_NAME', 'id_field' => 'OBJECTID'],
    ['dataset_key' => 'sa_rest_areas_state_maintained', 'path' => 'data/sources/vanassist/sa/sa_rest_areas.geojson', 'format' => 'geojson', 'name_field' => 'deptasseti', 'id_field' => 'uid'],
    ['dataset_key' => 'tas_roadside_stops', 'path' => 'data/sources/vanassist/tas/tas_roadside_stops.geojson', 'format' => 'geojson', 'name_field' => 'SITE_NAME', 'id_field' => 'OBJECTID'],
    ['dataset_key' => 'tas_boat_ramps', 'path' => 'data/sources/vanassist/tas/tas_boat_ramps.geojson', 'format' => 'geojson', 'name_field' => 'NAME', 'id_field' => 'OBJECTID'],
    ['dataset_key' => 'act_public_toilet_assets', 'path' => 'data/sources/vanassist/act/act_public_toilet_assets.geojson', 'format' => 'geojson', 'name_field' => 'ASSET_NAME', 'id_field' => 'ASSET_ID'],
];

$service = new GovernmentDatasetService();
$report = [
    'mode' => $approve ? 'stage_and_approve' : 'stage_only',
    'generated_at' => gmdate('c'),
    'datasets' => [],
    'totals' => ['staged_jobs' => 0, 'approved' => 0, 'skipped_missing_file' => 0, 'skipped_missing_catalogue' => 0, 'errors' => 0],
];

foreach ($packs as $pack) {
    if ($only !== null && $pack['dataset_key'] !== $only) {
        continue;
    }
    $abs = BASE_PATH . '/' . $pack['path'];
    $entry = [
        'dataset_key' => $pack['dataset_key'],
        'path' => $pack['path'],
        'status' => 'pending',
    ];
    if (!is_file($abs)) {
        $entry['status'] = 'skipped_missing_file';
        $report['totals']['skipped_missing_file']++;
        $report['datasets'][] = $entry;
        continue;
    }

    $dataset = $service->findDatasetByKey($pack['dataset_key']);
    if ($dataset === null) {
        $entry['status'] = 'skipped_missing_catalogue';
        $report['totals']['skipped_missing_catalogue']++;
        $report['datasets'][] = $entry;
        continue;
    }

    try {
        $settings = json_decode((string) ($dataset['settings_json'] ?? '{}'), true);
        if (!is_array($settings)) {
            $settings = [];
        }
        $connector = $pack['format'] === 'csv' ? 'gov_csv' : 'gov_geojson';
        $settings['limit'] = max(50000, (int) ($settings['limit'] ?? 50000));
        if (!empty($pack['name_field'])) {
            $settings['name_field'] = (string) $pack['name_field'];
        }
        if (!empty($pack['id_field'])) {
            $settings['id_field'] = (string) $pack['id_field'];
        }
        Database::query(
            'UPDATE government_datasets SET is_enabled=1, connector_key=?, fetch_method=?, source_format=?, settings_json=?, last_checked_at=NOW() WHERE id=?',
            [
                $connector,
                $pack['format'] === 'csv' ? 'csv' : 'geojson',
                strtoupper($pack['format']),
                json_encode($settings, JSON_THROW_ON_ERROR),
                (int) $dataset['id'],
            ]
        );

        $result = $service->fetchDataset((int) $dataset['id'], null, null, $abs);
        $entry['status'] = 'staged';
        $entry['result'] = $result;
        $report['totals']['staged_jobs']++;
        $report['totals']['candidates_found'] = ($report['totals']['candidates_found'] ?? 0) + (int) ($result['found'] ?? 0);
        $report['totals']['candidates_new'] = ($report['totals']['candidates_new'] ?? 0) + (int) ($result['new'] ?? 0);

        if ($approve) {
            $approved = $service->approvePendingForDataset(
                (int) $dataset['id'],
                null,
                'Archived GREEN source import'
            );
            $entry['approved'] = $approved;
            $report['totals']['approved'] += $approved;
        }
    } catch (Throwable $e) {
        $entry['status'] = 'error';
        $entry['error'] = $e->getMessage();
        $report['totals']['errors']++;
    }

    $report['datasets'][] = $entry;
}

$out = BASE_PATH . '/data/sources/vanassist/reports/green-facility-import-' . date('Ymd-His') . '.json';
if (!is_dir(dirname($out))) {
    mkdir(dirname($out), 0775, true);
}
file_put_contents($out, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo "Report: {$out}\n";
