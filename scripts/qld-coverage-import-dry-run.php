<?php

declare(strict_types=1);

/**
 * Dry-run QLD publishable-minus-regulated candidates into review-queue shape.
 *
 * Default: write a report under storage/imports/qld-coverage/ — no DB writes.
 * Optional --apply is local/test only and still does not create public providers;
 * it only inserts pending data_source_import_candidates for admin review.
 *
 *   php scripts/qld-coverage-import-dry-run.php
 *   php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay
 *   php scripts/qld-coverage-import-dry-run.php --batch brisbane-moreton-bay --limit 50
 *   php scripts/qld-coverage-import-dry-run.php --apply   # local/test only
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
use App\Platform\DataSources\Connectors\OfflineQldCoverageConnector;
use App\Services\QldCoverageImportDryRunService;

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? array_values(array_filter($_SERVER['argv'], 'is_string')) : [];
$batch = QldCoverageImportDryRunService::DEFAULT_BATCH;
$limit = 0;
$apply = in_array('--apply', $args, true);
$listBatches = in_array('--list-batches', $args, true);

for ($i = 1, $n = count($args); $i < $n; $i++) {
    if ($args[$i] === '--batch' && isset($args[$i + 1])) {
        $batch = $args[++$i];
    } elseif ($args[$i] === '--limit' && isset($args[$i + 1])) {
        $limit = max(0, (int) $args[++$i]);
    }
}

$service = new QldCoverageImportDryRunService();
if ($listBatches) {
    foreach ($service->batchIds() as $id) {
        echo $id . "\n";
    }
    exit(0);
}

try {
    $report = $service->build($batch, $limit);
} catch (InvalidArgumentException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

$outDir = BASE_PATH . '/storage/imports/qld-coverage';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Cannot create {$outDir}\n");
    exit(1);
}

$reportPath = $outDir . '/dry-run-' . $batch . '.json';
$candidatesPath = $outDir . '/dry-run-' . $batch . '-candidates.jsonl';

$summary = $report;
unset($summary['candidates']);
$summary['candidate_report'] = 'storage/imports/qld-coverage/dry-run-' . $batch . '-candidates.jsonl';
$summary['mode'] = $apply ? 'apply-attempt' : 'dry-run';

file_put_contents($reportPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$fh = fopen($candidatesPath, 'wb');
if ($fh === false) {
    fwrite(STDERR, "Cannot write {$candidatesPath}\n");
    exit(1);
}
foreach ($report['candidates'] as $candidate) {
    fwrite($fh, json_encode($candidate, JSON_UNESCAPED_SLASHES) . "\n");
}
fclose($fh);

$applied = 0;
$skippedExisting = 0;
$applyError = null;

if ($apply) {
    if (!is_file(BASE_PATH . '/.env')) {
        fwrite(STDERR, "--apply requires a .env database configuration. Dry-run artefacts were still written.\n");
        exit(2);
    }
    Env::load(BASE_PATH . '/.env');
    Config::load(BASE_PATH . '/config');
    $environment = (string) Config::get('app.env', 'production');
    if (!in_array($environment, ['local', 'test'], true)) {
        fwrite(STDERR, "Refusing --apply outside local/test. Dry-run artefacts were written; production was not modified.\n");
        exit(2);
    }

    try {
        $brandKey = 'vanassist';
        $brandId = (int) Database::scalar('SELECT id FROM brands WHERE brand_key=? OR slug=?', [$brandKey, $brandKey]);
        if ($brandId < 1) {
            $brandId = (int) Database::scalar("SELECT id FROM brands ORDER BY id LIMIT 1");
        }
        if ($brandId < 1) {
            throw new RuntimeException('No brand row available for import candidates.');
        }

        $connectorId = (int) Database::scalar("SELECT id FROM data_source_connectors WHERE connector_key='qld_coverage_offline'");
        if ($connectorId < 1) {
            $connectorId = Database::insert(
                'INSERT INTO data_source_connectors (connector_key, name, connector_class, status, daily_request_limit, daily_budget_aud, estimated_request_cost_aud, settings_json, created_at, updated_at) VALUES (?, ?, ?, ?, 0, 0, 0, ?, NOW(), NOW())',
                [
                    'qld_coverage_offline',
                    'QLD coverage offline pack',
                    OfflineQldCoverageConnector::class,
                    'configured',
                    json_encode(['offline' => true], JSON_THROW_ON_ERROR),
                ]
            );
        }

        $jobId = Database::insert(
            'INSERT INTO data_source_import_jobs (connector_id, brand_id, mapping_id, status, scope_json, requested_by, started_at, created_at) VALUES (?,?,NULL,\'review\',?,NULL,NOW(),NOW())',
            [
                $connectorId,
                $brandId,
                json_encode([
                    'source' => 'qld-coverage-dry-run',
                    'batch' => $batch,
                    'note' => 'Pending review only; not published',
                ], JSON_THROW_ON_ERROR),
            ]
        );

        foreach ($report['candidates'] as $candidate) {
            if (!empty($candidate['google_places_provenance'])) {
                // Places rows stay report-only until independent retention is confirmed.
                $skippedExisting++;
                continue;
            }
            $inserted = Database::affecting(
                'INSERT IGNORE INTO data_source_import_candidates (job_id, connector_id, brand_id, category_id, external_id, business_name, formatted_address, phone, website, latitude, longitude, raw_json, confidence, duplicate_provider_id, duplicate_score, duplicate_reasons_json, created_at, expires_at) VALUES (?,?,?,NULL,?,?,?,?,?,?,?,?,?,NULL,NULL,NULL,NOW(),DATE_ADD(NOW(), INTERVAL 30 DAY))',
                [
                    $jobId,
                    $connectorId,
                    $brandId,
                    (string) $candidate['external_id'],
                    (string) $candidate['business_name'],
                    $candidate['formatted_address'] ?? null,
                    $candidate['phone'] ?? null,
                    $candidate['website'] ?? null,
                    $candidate['latitude'] ?? null,
                    $candidate['longitude'] ?? null,
                    json_encode($candidate, JSON_THROW_ON_ERROR),
                    (int) $candidate['confidence'],
                ]
            );
            if ($inserted > 0) {
                $applied++;
            } else {
                $skippedExisting++;
            }
        }

        Database::query(
            'UPDATE data_source_import_jobs SET candidates_found=?, candidates_new=?, completed_at=NOW() WHERE id=?',
            [count($report['candidates']), $applied, $jobId]
        );
        $summary['mode'] = 'applied-to-review-queue';
        $summary['job_id'] = $jobId;
        $summary['inserted_pending'] = $applied;
        $summary['skipped'] = $skippedExisting;
        file_put_contents($reportPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    } catch (Throwable $e) {
        $applyError = $e->getMessage();
        fwrite(STDERR, 'Apply failed: ' . $applyError . "\n");
        $summary['apply_error'] = $applyError;
        file_put_contents($reportPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
        exit(1);
    }
}

echo json_encode([
    'mode' => $summary['mode'],
    'batch_id' => $batch,
    'batch_matched' => $report['batch_matched'],
    'regulated_held_total' => $report['regulated_held_total'],
    'regulated_excluded_from_publishable' => $report['regulated_excluded_from_publishable'],
    'places_provenance_flagged' => $report['places_provenance_flagged'],
    'eligible_for_apply_estimate' => $report['eligible_for_apply_estimate'],
    'report' => 'storage/imports/qld-coverage/dry-run-' . $batch . '.json',
    'candidates' => 'storage/imports/qld-coverage/dry-run-' . $batch . '-candidates.jsonl',
    'inserted_pending' => $applied,
    'skipped' => $skippedExisting,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
