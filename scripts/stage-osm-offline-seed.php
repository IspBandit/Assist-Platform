<?php

declare(strict_types=1);

/**
 * Stage offline OSM seed hits into DATA-006 import candidates (trusted_review).
 * Never calls live Overpass. Never auto-publishes.
 *
 *   php scripts/stage-osm-offline-seed.php
 *   php scripts/stage-osm-offline-seed.php --query=autoelec --state=ACT --limit=50
 *   php scripts/stage-osm-offline-seed.php --brand=vanassist --dry-run
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
use App\Platform\AiSearch\Staging\DatasetTrustPolicy;
use App\Platform\AiSearch\Staging\DraftCandidateService;
use App\Platform\DataSources\Connectors\OsmOfflineSeedConnector;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];

$dryRun = in_array('--dry-run', $args, true);
$brandKey = 'vanassist';
$query = '';
$state = '';
$limit = 100;
foreach ($args as $i => $arg) {
    if (str_starts_with($arg, '--brand=')) {
        $brandKey = substr($arg, 8) ?: 'vanassist';
    } elseif (str_starts_with($arg, '--query=')) {
        $query = substr($arg, 8);
    } elseif (str_starts_with($arg, '--state=')) {
        $state = substr($arg, 8);
    } elseif (str_starts_with($arg, '--limit=')) {
        $limit = max(1, min(500, (int) substr($arg, 8)));
    }
}

if (!(bool) config('ai_search.osm_offline_enabled', false) && !in_array('--force', $args, true)) {
    fwrite(STDERR, "Set AI_OSM_OFFLINE_ENABLED=1 (or pass --force) to stage OSM offline seed hits.\n");
    exit(1);
}

$brandId = (int) Database::scalar(
    'SELECT id FROM brands WHERE brand_key = ? LIMIT 1',
    [$brandKey]
);
if ($brandId < 1) {
    fwrite(STDERR, "Brand not found: {$brandKey}\n");
    exit(1);
}

$connector = new OsmOfflineSeedConnector();
$hits = $connector->search(
    ['query' => $query, 'state' => $state, 'limit' => $limit],
    [],
    ['force' => true, 'limit' => $limit]
);

$summary = [
    'brand' => $brandKey,
    'brand_id' => $brandId,
    'query' => $query,
    'state' => $state,
    'matched' => count($hits),
    'dry_run' => $dryRun,
];

if ($dryRun) {
    $summary['sample'] = array_slice(array_map(static fn (array $h): string => (string) $h['business_name'], $hits), 0, 10);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
}

$result = (new DraftCandidateService())->stageHits(
    $brandId,
    OsmOfflineSeedConnector::KEY,
    $hits,
    DatasetTrustPolicy::TRUSTED_REVIEW
);
$summary['staged'] = $result['staged'];
$summary['skipped'] = $result['skipped'];
$summary['job_id'] = $result['job_id'];
echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
