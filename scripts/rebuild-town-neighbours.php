#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Rebuild town_neighbours from measurable town coordinates (VAN-011).
 *
 * Usage:
 *   php scripts/rebuild-town-neighbours.php
 *   php scripts/rebuild-town-neighbours.php --dry-run
 *   php scripts/rebuild-town-neighbours.php --force
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\Geography\TownNeighbourGraphBuilder;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$args = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$dryRun = in_array('--dry-run', $args, true);
$force = in_array('--force', $args, true);

try {
    $result = TownNeighbourGraphBuilder::rebuild($dryRun, $force);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}

if (!empty($result['skipped'])) {
    fwrite(STDOUT, 'Skipped: ' . (string) ($result['note'] ?? 'unchanged') . "\n");
    if (isset($result['edges'])) {
        fwrite(STDOUT, 'Existing edges: ' . (int) $result['edges'] . "\n");
    }
    exit(0);
}

$prefix = !empty($result['dry_run']) ? 'Dry-run' : 'Rebuilt';
fwrite(STDOUT, sprintf(
    "%s town neighbour graph: %d measurable towns → %d edges (max %d km, limit %d).\n",
    $prefix,
    (int) ($result['towns'] ?? 0),
    (int) ($result['edges'] ?? 0),
    (int) config('geo.neighbour_max_km', 50),
    (int) config('geo.neighbour_limit', 8)
));
if (isset($result['fingerprint'])) {
    fwrite(STDOUT, 'Fingerprint: ' . (string) $result['fingerprint'] . "\n");
}

exit(0);
