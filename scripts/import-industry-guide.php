<?php
/**
 * Import extracted industry guide data into VanAssist traveller facilities.
 *
 * Imports parks/campgrounds from extracted industry association holiday guides
 * and regional tourism PDFs through the existing GovernmentDatasetService
 * review-first pipeline.
 *
 * Usage:
 *   php scripts/import-industry-guide.php industry_big4_holiday_guide_2026
 *   php scripts/import-industry-guide.php industry_big4_holiday_guide_2026 --apply
 *   php scripts/import-industry-guide.php regional_drive_queensland_2026 --force --apply
 *
 * Extraction must be completed first (see tools/industry_guides/extract_*.py).
 *
 * Deduplication: The import pipeline guarantees source-based idempotency.
 * Same external_id from same source always updates (never duplicates).
 * See docs/data/VANASSIST_DEDUPLICATION.md.
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

if (count($argvList) < 2) {
    fwrite(STDERR, "Usage: php scripts/import-industry-guide.php <dataset_key> [--apply] [--force]\n");
    fwrite(STDERR, "\nExamples:\n");
    fwrite(STDERR, "  php scripts/import-industry-guide.php industry_big4_holiday_guide_2026\n");
    fwrite(STDERR, "  php scripts/import-industry-guide.php regional_drive_queensland_2026 --apply\n");
    fwrite(STDERR, "\nAvailable datasets:\n");
    fwrite(STDERR, "  - industry_big4_holiday_guide_2026\n");
    fwrite(STDERR, "  - industry_nsw_ccia_holiday_guide_2026\n");
    fwrite(STDERR, "  - regional_drive_queensland_2026\n");
    fwrite(STDERR, "  - regional_barcoo_visitor_guide\n");
    exit(1);
}

$datasetKey = strtolower(trim($argvList[1]));
$apply = in_array('--apply', $argvList, true);
$force = in_array('--force', $argvList, true);

$env = strtolower((string) (getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? 'production')));
if (!$force && !in_array($env, ['local', 'development', 'dev', 'testing', 'test', 'staging'], true)) {
    fwrite(STDERR, "Refusing to run outside non-production APP_ENV without --force (APP_ENV={$env}).\n");
    exit(1);
}

// Map dataset keys to seed directories
$seedMap = [
    'industry_big4_holiday_guide_2026' => 'industry_big4_2026',
    'industry_nsw_ccia_holiday_guide_2026' => 'industry_nsw_ccia_2026',
    'regional_drive_queensland_2026' => 'regional_drive_qld_2026',
    'regional_barcoo_visitor_guide' => 'regional_barcoo',
    'regional_scenic_rim_visitor_guide' => 'regional_scenic_rim',
];

if (!isset($seedMap[$datasetKey])) {
    fwrite(STDERR, "Unknown dataset key: {$datasetKey}\n");
    fwrite(STDERR, "Available: " . implode(', ', array_keys($seedMap)) . "\n");
    exit(1);
}

$seedDir = $seedMap[$datasetKey];
$parksFile = BASE_PATH . "/database/seeds/{$seedDir}/parks.json";

if (!file_exists($parksFile)) {
    fwrite(STDERR, "Seed file not found: {$parksFile}\n");
    fwrite(STDERR, "Run extraction first: python tools/industry_guides/extract_*.py\n");
    exit(1);
}

$service = new GovernmentDatasetService();
$dataset = $service->findDatasetByKey($datasetKey);

if ($dataset === null) {
    fwrite(STDERR, "Dataset not registered in government_datasets: {$datasetKey}\n");
    fwrite(STDERR, "Register it via /admin/data-sources/datasets first.\n");
    exit(1);
}

echo "Dataset: {$dataset['title']}\n";
echo "Seed file: {$parksFile}\n";
echo "Mode: " . ($apply ? 'APPLY (will stage candidates)' : 'DRY RUN (review only)') . "\n\n";

$jsonContent = file_get_contents($parksFile);
if ($jsonContent === false) {
    fwrite(STDERR, "Failed to read seed file.\n");
    exit(1);
}

$rows = json_decode($jsonContent, true);
if (!is_array($rows)) {
    fwrite(STDERR, "Invalid JSON in seed file.\n");
    exit(1);
}

echo "Parsed " . count($rows) . " park records from seed file.\n";

// Validate required fields
$validRows = [];
$invalidCount = 0;
foreach ($rows as $row) {
    if (!is_array($row)) {
        $invalidCount++;
        continue;
    }
    $externalId = trim((string) ($row['external_id'] ?? ''));
    $name = trim((string) ($row['name'] ?? ''));
    if ($externalId === '' || $name === '') {
        $invalidCount++;
        continue;
    }
    $validRows[] = $row;
}

echo "Valid records: " . count($validRows) . "\n";
if ($invalidCount > 0) {
    echo "Invalid records (missing external_id or name): {$invalidCount}\n";
}

if (count($validRows) === 0) {
    fwrite(STDERR, "\nNo valid records to import.\n");
    exit(1);
}

if (!$apply) {
    echo "\n✓ DRY RUN complete. Use --apply to stage candidates.\n";
    exit(0);
}

// Get VanAssist brand ID
$brand = Database::selectOne(
    'SELECT id FROM brands WHERE slug = ? LIMIT 1',
    ['vanassist']
);
if ($brand === null) {
    fwrite(STDERR, "VanAssist brand not found in database.\n");
    exit(1);
}
$brandId = (int) $brand['id'];

// Get admin user for provenance
$admin = Database::selectOne(
    'SELECT id FROM users WHERE email = ? AND is_admin = 1 LIMIT 1',
    ['glen@condren.digital']
);
$userId = $admin !== null ? (int) $admin['id'] : null;

echo "\nIngesting into traveller_facility_import_candidates...\n";

try {
    $result = $service->ingestAssistRicRows(
        (int) $dataset['id'],
        $validRows,
        $brandId,
        $userId,
        [
            'source_key' => $datasetKey,
            'seed_file' => basename($parksFile),
            'extraction_date' => date('Y-m-d'),
        ]
    );

    echo "\n✓ Import complete:\n";
    echo "  Job ID: {$result['job_id']}\n";
    echo "  Candidates found: {$result['found']}\n";
    echo "  New candidates staged: {$result['new']}\n";
    echo "  Duplicates/existing: " . ($result['found'] - $result['new']) . "\n";
    echo "\nReview candidates at: /admin/data-sources/datasets/candidates/{$result['job_id']}\n";
    echo "\nTo auto-approve trusted candidates:\n";
    echo "  php scripts/approve-pending-green-facilities.php --job-id={$result['job_id']}\n";

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "\nImport failed: {$e->getMessage()}\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}
