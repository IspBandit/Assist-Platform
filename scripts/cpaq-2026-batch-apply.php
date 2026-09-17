<?php

declare(strict_types=1);

/**
 * Batched CPAQ 2026 apply helper.
 *
 * Splits parks/trade seed JSON into chunks and commits each chunk via
 * Cpaq2026ImportService so long imports survive MariaDB connection drops.
 *
 * Usage:
 *   php scripts/cpaq-2026-batch-apply.php
 *   php scripts/cpaq-2026-batch-apply.php --batch-size=25
 *
 * Docs: docs/CPAQ_2026_IMPORT.md (DATA-001 / VAN-001)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$candidateBases = [
    dirname(__DIR__),
    dirname(__DIR__, 2),
    '/var/www/html',
];
$basePath = null;
foreach ($candidateBases as $candidate) {
    if (is_file($candidate . '/bootstrap/autoload.php')) {
        $basePath = $candidate;
        break;
    }
}
if ($basePath === null) {
    fwrite(STDERR, "Unable to locate application BASE_PATH.\n");
    exit(1);
}

define('BASE_PATH', $basePath);
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;
use App\Services\AuditLog;
use App\Services\Cpaq2026ImportService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$arguments = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];

$batchSize = 25;
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '--batch-size=')) {
        $batchSize = max(1, (int) substr($argument, strlen('--batch-size=')));
    }
}

$seed = BASE_PATH . '/database/seeds/cpaq-2026';
$tmp = BASE_PATH . '/storage/imports/cpaq-batches';
if (!is_dir($tmp) && !mkdir($tmp, 0775, true) && !is_dir($tmp)) {
    fwrite(STDERR, "Unable to create batch directory: {$tmp}\n");
    exit(1);
}

$parks = json_decode((string) file_get_contents($seed . '/parks.json'), true, 512, JSON_THROW_ON_ERROR);
$trade = json_decode((string) file_get_contents($seed . '/trade.json'), true, 512, JSON_THROW_ON_ERROR);
if (!is_array($parks) || !is_array($trade)) {
    fwrite(STDERR, "Invalid CPAQ seed JSON.\n");
    exit(1);
}

$totals = [
    'parks' => ['parsed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped_duplicate' => 0, 'unresolved' => 0, 'failed' => 0, 'geocode_skipped' => 0],
    'trade' => ['parsed' => 0, 'inserted' => 0, 'updated' => 0, 'skipped_duplicate' => 0, 'unresolved' => 0, 'failed' => 0],
];

$mergeCounts = static function (array &$into, array $from): void {
    foreach ($from as $key => $value) {
        if (isset($into[$key]) && is_int($value)) {
            $into[$key] += $value;
        }
    }
};

$parkChunks = array_chunk($parks, $batchSize);
foreach ($parkChunks as $index => $chunk) {
    $dir = $tmp . '/p' . $index;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Unable to create {$dir}\n");
        exit(1);
    }
    file_put_contents($dir . '/parks.json', json_encode($chunk, JSON_UNESCAPED_UNICODE));
    file_put_contents($dir . '/trade.json', '[]');
    fwrite(STDOUT, 'Parks batch ' . ($index + 1) . '/' . count($parkChunks) . ' (' . count($chunk) . ")...\n");
    $summary = (new Cpaq2026ImportService($dir))->import(true, true, false);
    $mergeCounts($totals['parks'], $summary['parks']);
    fwrite(STDOUT, json_encode($summary['parks'], JSON_UNESCAPED_SLASHES) . "\n");
}

$tradeChunks = array_chunk($trade, $batchSize);
foreach ($tradeChunks as $index => $chunk) {
    $dir = $tmp . '/t' . $index;
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        fwrite(STDERR, "Unable to create {$dir}\n");
        exit(1);
    }
    file_put_contents($dir . '/parks.json', '[]');
    file_put_contents($dir . '/trade.json', json_encode($chunk, JSON_UNESCAPED_UNICODE));
    fwrite(STDOUT, 'Trade batch ' . ($index + 1) . '/' . count($tradeChunks) . ' (' . count($chunk) . ")...\n");
    $summary = (new Cpaq2026ImportService($dir))->import(true, false, true);
    $mergeCounts($totals['trade'], $summary['trade']);
    fwrite(STDOUT, json_encode($summary['trade'], JSON_UNESCAPED_SLASHES) . "\n");
}

$pdo = Database::connection();
$report = [
    'mode' => 'batched-apply',
    'batch_size' => $batchSize,
    'batch_totals' => $totals,
    'db' => [
        'parks_total' => (int) $pdo->query('SELECT COUNT(*) FROM caravan_parks WHERE deleted_at IS NULL')->fetchColumn(),
        'parks_cpaq' => (int) $pdo->query("SELECT COUNT(*) FROM caravan_parks WHERE source_type='cpaq' AND deleted_at IS NULL")->fetchColumn(),
        'providers_total' => (int) $pdo->query('SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL')->fetchColumn(),
        'providers_cpaq' => (int) $pdo->query("SELECT COUNT(*) FROM provider_source_records WHERE source_key='cpaq-2026'")->fetchColumn(),
        'facility_claims' => (int) $pdo->query("SELECT COUNT(*) FROM stay_facility_claims WHERE source_name='CPAQ Explore Queensland Caravan Parks Directory 2026' AND superseded_at IS NULL")->fetchColumn(),
    ],
];

AuditLog::record(
    'cpaq_2026.import_applied',
    'cpaq_import',
    null,
    null,
    json_encode([
        'parks' => $totals['parks'],
        'trade' => $totals['trade'],
        'mode' => 'batched-apply',
        'batch_size' => $batchSize,
    ], JSON_UNESCAPED_SLASHES)
);

$reportPath = BASE_PATH . '/storage/imports/cpaq-batch-apply-report.json';
file_put_contents($reportPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);
