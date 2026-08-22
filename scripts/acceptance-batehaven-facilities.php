<?php

declare(strict_types=1);

/**
 * VA-ACCEPT-BATEHAVEN-001 — non-production acceptance runner.
 *
 *   php scripts/acceptance-batehaven-facilities.php --dry-run
 *   php scripts/acceptance-batehaven-facilities.php --import-approve --radius=50
 *
 * --dry-run: fixtures + deterministic intent only (no MariaDB).
 * Full run: temporary staging flags, Ask orchestrator, then restore flags.
 * Paid AI forced off for the run. Refuses production APP_ENV without --force.
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
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\SearchOrchestrator;
use App\Platform\AiSearch\Support\AiSearchFeature;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;
use App\Services\GovernmentDatasetService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$force = in_array('--force', $args, true);
$dryRun = in_array('--dry-run', $args, true);
$importApprove = in_array('--import-approve', $args, true);
$radius = 50;
foreach ($args as $arg) {
    if (str_starts_with($arg, '--radius=')) {
        $radius = max(1, min(100, (int) substr($arg, 9)));
    }
}

$appEnv = strtolower(trim((string) Env::get('APP_ENV', 'production')));
if (!in_array($appEnv, ['local', 'development', 'dev', 'testing', 'test', 'staging'], true) && !$force) {
    fwrite(STDERR, "Refusing acceptance run when APP_ENV={$appEnv}. Use non-prod or --force.\n");
    exit(1);
}

$query = 'public toilets and dump points near Batehaven, NSW';
$batehavenLat = -35.7325;
$batehavenLng = 150.1985;

$report = [
    'scenario' => 'VA-ACCEPT-BATEHAVEN-001',
    'mode' => $dryRun ? 'dry-run' : 'full',
    'env' => $appEnv,
    'query' => $query,
    'radius_km' => $radius,
    'origin' => ['lat' => $batehavenLat, 'lng' => $batehavenLng],
    'git_sha' => trim((string) @shell_exec(
        'git rev-parse --short HEAD ' . (PHP_OS_FAMILY === 'Windows' ? '2>NUL' : '2>/dev/null')
    )) ?: null,
    'steps' => [],
    'result' => 'FAIL',
];

$intent = (new IntentRuleEngine())->interpret($query);
$intentOk = in_array('public_toilet', $intent->facilityTypeKeys, true)
    && in_array('dump_point', $intent->facilityTypeKeys, true)
    && $intent->locationText !== null
    && stripos($intent->locationText, 'Batehaven') !== false;
$report['steps'][] = [
    'intent' => [
        'type' => $intent->intentType,
        'facilities' => $intent->facilityTypeKeys,
        'location' => $intent->locationText,
        'adapters' => $intent->adapterKeys,
        'source' => $intent->source,
        'ok' => $intentOk,
    ],
];

$fixtures = [
    'demo-public-toilets.csv' => is_file(BASE_PATH . '/resources/datasets/demo-public-toilets.csv')
        && str_contains((string) file_get_contents(BASE_PATH . '/resources/datasets/demo-public-toilets.csv'), 'Batehaven'),
    'demo-dump-points.geojson' => is_file(BASE_PATH . '/resources/datasets/demo-dump-points.geojson')
        && str_contains((string) file_get_contents(BASE_PATH . '/resources/datasets/demo-dump-points.geojson'), 'Batemans Bay'),
    'demo-drinking-water.csv' => is_file(BASE_PATH . '/resources/datasets/demo-drinking-water.csv')
        && str_contains((string) file_get_contents(BASE_PATH . '/resources/datasets/demo-drinking-water.csv'), 'Batehaven'),
];
$report['steps'][] = ['fixtures' => $fixtures];

if ($dryRun) {
    $report['result'] = ($intentOk && $fixtures['demo-public-toilets.csv'] && $fixtures['demo-dump-points.geojson'])
        ? 'PASS'
        : 'FAIL';
    $report['reason'] = $report['result'] === 'PASS'
        ? 'Dry-run: intent + Batehaven toilet/dump fixtures OK. Full DB Ask run still required when MariaDB is configured.'
        : 'Dry-run failed intent or fixture checks.';
    $out = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    echo $out;
    $evidenceDir = BASE_PATH . '/docs/evidence/vanassist-readiness-2026-08-02';
    if (is_dir($evidenceDir) || @mkdir($evidenceDir, 0775, true)) {
        file_put_contents($evidenceDir . '/VA_ACCEPT_BATEHAVEN_001_dry_run.json', $out);
    }
    exit($report['result'] === 'PASS' ? 0 : 1);
}

$dbUser = trim((string) Env::get('DB_USER', ''));
$dbName = trim((string) Env::get('DB_NAME', ''));
if ($dbUser === '' || $dbName === '') {
    $report['result'] = 'FAIL';
    $report['reason'] = 'MariaDB not configured (DB_USER/DB_NAME empty). Configure .env or use --dry-run / unit harness.';
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(1);
}

$prevAsk = false;
$prevFacilities = false;
$prevAi = false;
try {
    $prevAsk = FeatureFlag::enabled(AiSearchFeature::FLAG, false);
    $prevFacilities = FeatureFlag::enabled(TravellerFacilitiesFeature::FLAG, false);
    $aiRow = Database::selectOne('SELECT ai_enabled FROM ai_settings WHERE id = 1 LIMIT 1');
    $prevAi = $aiRow !== null && (int) ($aiRow['ai_enabled'] ?? 0) === 1;
} catch (Throwable $e) {
    $report['result'] = 'FAIL';
    $report['reason'] = 'Database connection failed: ' . $e->getMessage();
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
    exit(1);
}

try {
    if ($importApprove) {
        $brandId = (int) Database::scalar(
            "SELECT id FROM brands WHERE brand_key = 'vanassist' LIMIT 1"
        );
        if ($brandId < 1) {
            throw new RuntimeException('vanassist brand missing');
        }
        $service = new GovernmentDatasetService();
        foreach ([
            'demo_geojson_dump_points',
            'demo_csv_public_toilets',
            'demo_csv_drinking_water',
            'demo_csv_rest_areas',
            'demo_csv_visitor_information',
        ] as $datasetKey) {
            $row = Database::selectOne(
                'SELECT id FROM government_datasets WHERE dataset_key = ? LIMIT 1',
                [$datasetKey]
            );
            if ($row === null) {
                $report['steps'][] = ['import' => $datasetKey, 'error' => 'catalogue missing — apply migrations 093/098'];
                continue;
            }
            $imported = $service->importFixture((int) $row['id'], $brandId, null);
            $report['steps'][] = ['import' => $datasetKey, 'result' => $imported];
        }
        $approved = 0;
        foreach ($service->pendingCandidates(300) as $candidate) {
            $service->reviewCandidate((int) $candidate['id'], 'approve', null, 'Batehaven acceptance bootstrap');
            $approved++;
        }
        $report['steps'][] = ['approve_pending' => $approved];
    }

    $coverage = Database::select(
        "SELECT facility_type, COUNT(*) AS c
         FROM traveller_facilities
         WHERE deleted_at IS NULL AND status = 'active'
           AND verification_status IN ('reviewed', 'verified')
           AND facility_type IN ('public_toilet', 'dump_point')
           AND latitude BETWEEN ? AND ?
           AND longitude BETWEEN ? AND ?
         GROUP BY facility_type",
        [
            $batehavenLat - ($radius / 111.32),
            $batehavenLat + ($radius / 111.32),
            $batehavenLng - ($radius / 100.0),
            $batehavenLng + ($radius / 100.0),
        ]
    );
    $byType = ['public_toilet' => 0, 'dump_point' => 0];
    foreach ($coverage as $row) {
        $byType[(string) $row['facility_type']] = (int) $row['c'];
    }
    $report['steps'][] = ['facility_coverage_near_batehaven' => $byType];

    FeatureFlag::set(AiSearchFeature::FLAG, true);
    FeatureFlag::set(TravellerFacilitiesFeature::FLAG, true);
    Database::query('UPDATE ai_settings SET ai_enabled = 0, openai_enabled = 0 WHERE id = 1');

    $brandRow = Database::selectOne(
        "SELECT id, brand_key FROM brands WHERE brand_key = 'vanassist' LIMIT 1"
    );
    if ($brandRow === null) {
        throw new RuntimeException('vanassist brand missing');
    }

    $result = (new SearchOrchestrator())->handle(new SearchRequest(
        rawQuery: $query,
        brandKey: 'vanassist',
        brandDatabaseId: (int) $brandRow['id'],
        latitude: $batehavenLat,
        longitude: $batehavenLng,
        radiusKm: $radius,
        requestId: 'accept-batehaven-' . bin2hex(random_bytes(4)),
        channel: 'acceptance',
        sessionId: null,
    ));

    $facilityTypesShown = [];
    foreach ($result->facilities as $f) {
        $facilityTypesShown[] = (string) ($f['facility_type'] ?? '');
    }
    $report['steps'][] = [
        'ask' => [
            'facility_count' => count($result->facilities),
            'facility_types' => array_values(array_unique(array_filter($facilityTypesShown))),
            'provider_count' => count($result->providers),
            'stay_count' => count($result->stays),
            'external_count' => count($result->externals),
            'fallback' => $result->fallbackReason,
            'intent_source' => $result->intent->source,
            'paid_ai_used' => $result->intent->source === 'ai',
        ],
    ];

    $hasToilet = in_array('public_toilet', $facilityTypesShown, true);
    $hasDumpResult = in_array('dump_point', $facilityTypesShown, true);
    $facilitiesInStays = false;
    foreach ($result->stays as $stay) {
        $type = (string) ($stay['facility_type'] ?? '');
        if ($type === 'public_toilet' || $type === 'dump_point') {
            $facilitiesInStays = true;
        }
    }

    if ($result->intent->source === 'ai') {
        $report['result'] = 'FAIL';
        $report['reason'] = 'Paid AI was used; deterministic path required.';
    } elseif ($facilitiesInStays) {
        $report['result'] = 'FAIL';
        $report['reason'] = 'Facility types appeared in stays bucket.';
    } elseif (!$intentOk) {
        $report['result'] = 'FAIL';
        $report['reason'] = 'Intent did not resolve toilets+dump+Batehaven.';
    } elseif ($hasToilet && $hasDumpResult) {
        $report['result'] = 'PASS';
        $report['reason'] = 'Ask returned toilet and dump facilities near Batehaven.';
    } elseif ($byType['public_toilet'] > 0 && $byType['dump_point'] > 0) {
        $report['result'] = 'CONDITIONAL';
        $report['reason'] = 'DB coverage exists but Ask facilities section incomplete.';
    } elseif ($byType['public_toilet'] > 0 || $byType['dump_point'] > 0) {
        $report['result'] = 'CONDITIONAL';
        $report['reason'] = 'Partial coverage only; run --import-approve.';
    } else {
        $report['result'] = 'FAIL';
        $report['reason'] = 'No reviewed toilet/dump coverage near Batehaven.';
    }
} catch (Throwable $e) {
    $report['result'] = 'FAIL';
    $report['reason'] = $e->getMessage();
} finally {
    try {
        FeatureFlag::set(AiSearchFeature::FLAG, $prevAsk);
        FeatureFlag::set(TravellerFacilitiesFeature::FLAG, $prevFacilities);
        if ($prevAi) {
            Database::query('UPDATE ai_settings SET ai_enabled = 1 WHERE id = 1');
        } else {
            Database::query('UPDATE ai_settings SET ai_enabled = 0, openai_enabled = 0 WHERE id = 1');
        }
        $report['steps'][] = [
            'flags_restored' => [
                'assist_ai_search' => $prevAsk,
                'assist_ai_traveller_facilities' => $prevFacilities,
            ],
        ];
    } catch (Throwable $e) {
        $report['steps'][] = ['flag_restore_error' => $e->getMessage()];
        $report['result'] = 'FAIL';
        $report['reason'] = 'Flag restore failed: ' . $e->getMessage();
    }
}

$out = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
echo $out;
$evidenceDir = BASE_PATH . '/docs/evidence/vanassist-readiness-2026-08-02';
if (!is_writable($evidenceDir)) {
    $evidenceDir = BASE_PATH . '/storage/evidence/vanassist-readiness-2026-08-02';
}
if ((is_dir($evidenceDir) || @mkdir($evidenceDir, 0775, true)) && is_writable($evidenceDir)) {
    file_put_contents($evidenceDir . '/VA_ACCEPT_BATEHAVEN_001.json', $out);
}

exit($report['result'] === 'PASS' ? 0 : 1);
