<?php

declare(strict_types=1);

/**
 * Import demo traveller-facility fixtures (DATA-012) for non-production bootstrap.
 *
 * Default: import only (pending review candidates).
 * With --approve: also approve pending candidates into traveller_facilities
 * (status=active, verification_status=reviewed). Never writes caravan_parks.
 *
 *   php scripts/import-demo-traveller-facilities.php
 *   php scripts/import-demo-traveller-facilities.php --approve
 *   php scripts/import-demo-traveller-facilities.php --approve --brand=vanassist
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

$appEnv = strtolower(trim((string) Env::get('APP_ENV', 'production')));
$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$force = in_array('--force', $args, true);
if (!in_array($appEnv, ['local', 'development', 'dev', 'testing', 'test'], true) && !$force) {
    fwrite(STDERR, "Refusing to import demo facilities when APP_ENV={$appEnv}. Use --force only for intentional non-prod bootstrap.\n");
    exit(1);
}

$approve = in_array('--approve', $args, true);
$brandKey = 'vanassist';
foreach ($args as $i => $arg) {
    if (str_starts_with($arg, '--brand=')) {
        $brandKey = substr($arg, 8) ?: 'vanassist';
    } elseif ($arg === '--brand' && isset($args[$i + 1])) {
        $brandKey = (string) $args[$i + 1];
    }
}

$brandId = (int) Database::scalar(
    'SELECT id FROM brands WHERE brand_key = ? LIMIT 1',
    [$brandKey]
);
if ($brandId < 1) {
    fwrite(STDERR, "Brand not found: {$brandKey}\n");
    exit(1);
}

$service = new GovernmentDatasetService();
$keys = [
    'demo_geojson_dump_points',
    'demo_csv_public_toilets',
    'demo_csv_drinking_water',
    'demo_csv_rest_areas',
    'demo_csv_visitor_information',
];
$summary = [
    'brand' => $brandKey,
    'brand_id' => $brandId,
    'approve' => $approve,
    'imports' => [],
    'approved' => 0,
    'rejected_errors' => [],
];

foreach ($keys as $datasetKey) {
    $row = Database::selectOne(
        'SELECT id, dataset_key, title FROM government_datasets WHERE dataset_key = ? LIMIT 1',
        [$datasetKey]
    );
    if ($row === null) {
        $summary['imports'][] = [
            'dataset_key' => $datasetKey,
            'error' => 'Catalogue row missing — apply migration 093.',
        ];
        continue;
    }
    try {
        $result = $service->importFixture((int) $row['id'], $brandId, null);
        $summary['imports'][] = [
            'dataset_key' => $datasetKey,
            'title' => (string) $row['title'],
            'job_id' => $result['job_id'],
            'found' => $result['found'],
            'new' => $result['new'],
        ];
    } catch (Throwable $e) {
        $summary['imports'][] = [
            'dataset_key' => $datasetKey,
            'error' => $e->getMessage(),
        ];
    }
}

if ($approve) {
    $pending = $service->pendingCandidates(300);
    foreach ($pending as $candidate) {
        try {
            $service->reviewCandidate((int) $candidate['id'], 'approve', null, 'CLI demo fixture bootstrap');
            $summary['approved']++;
        } catch (Throwable $e) {
            $summary['rejected_errors'][] = [
                'candidate_id' => (int) $candidate['id'],
                'error' => $e->getMessage(),
            ];
        }
    }
}

$active = 0;
try {
    $active = (int) Database::scalar(
        "SELECT COUNT(*) FROM traveller_facilities
         WHERE deleted_at IS NULL AND status = 'active'
           AND verification_status IN ('reviewed', 'verified')"
    );
} catch (Throwable) {
    $active = 0;
}
$summary['active_reviewed_facilities'] = $active;

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";

$hadImportError = false;
foreach ($summary['imports'] as $import) {
    if (isset($import['error'])) {
        $hadImportError = true;
        break;
    }
}
exit($hadImportError || $summary['rejected_errors'] !== [] ? 1 : 0);
