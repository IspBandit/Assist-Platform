<?php

declare(strict_types=1);

/**
 * Town coverage report (local vs serving providers).
 *
 * Usage:
 *   php scripts/coverage-report.php
 *   php scripts/coverage-report.php --thin 200
 *   php scripts/coverage-report.php --json
 *   php scripts/coverage-report.php --thin 500 --json > thin-towns.json
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\TownCoverageService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$json = in_array('--json', $argv, true);
$thinLimit = 0;
$thinIdx = array_search('--thin', $argv, true);
if ($thinIdx !== false && isset($argv[$thinIdx + 1]) && ctype_digit((string) $argv[$thinIdx + 1])) {
    $thinLimit = (int) $argv[$thinIdx + 1];
}

try {
    if ($thinLimit > 0) {
        $thin = TownCoverageService::thinTowns(2, $thinLimit);
        if ($json) {
            echo json_encode(['thin_towns' => $thin, 'count' => count($thin)], JSON_PRETTY_PRINT) . "\n";
            exit(0);
        }
        echo "Thin towns (0–2 local providers), top {$thinLimit}:\n";
        foreach ($thin as $row) {
            echo sprintf(
                "  %-4s %-32s local=%d serving=%s\n",
                $row['state'],
                $row['name'],
                $row['local_count'],
                $row['serving'] ? 'yes' : 'no'
            );
        }
        echo 'Total listed: ' . count($thin) . "\n";
        exit(0);
    }

    $report = TownCoverageService::report(25);
    if ($json) {
        echo json_encode($report, JSON_PRETTY_PRINT) . "\n";
        exit(0);
    }

    $t = $report['totals'];
    echo "Town coverage report\n";
    echo "====================\n";
    echo sprintf(
        "Towns: %s | local covered: %s%% | serving covered: %s%%\n",
        number_format((int) $t['towns']),
        $t['pct_local_covered'],
        $t['pct_serving_covered']
    );
    echo sprintf(
        "Local buckets — zero: %s | thin (1–2): %s | ok (3+): %s\n",
        number_format((int) $t['local_zero']),
        number_format((int) $t['local_thin']),
        number_format((int) $t['local_ok'])
    );
    echo sprintf(
        "Serving — covered: %s | none: %s\n\n",
        number_format((int) $t['serving_covered']),
        number_format((int) $t['serving_zero'])
    );

    echo sprintf("%-5s %8s %8s %8s %8s %10s %10s\n", 'State', 'Towns', 'Loc0', 'Loc1-2', 'Loc3+', 'ServeY', 'ServeN');
    echo str_repeat('-', 62) . "\n";
    foreach ($report['by_state'] as $row) {
        echo sprintf(
            "%-5s %8s %8s %8s %8s %10s %10s\n",
            $row['state'],
            number_format((int) $row['towns']),
            number_format((int) $row['local_zero']),
            number_format((int) $row['local_thin']),
            number_format((int) $row['local_ok']),
            number_format((int) $row['serving_covered']),
            number_format((int) $row['serving_zero'])
        );
    }

    if ($report['thin_sample'] !== []) {
        echo "\nSample thin towns (priority gap fills):\n";
        foreach ($report['thin_sample'] as $row) {
            echo sprintf(
                "  %-4s %-28s local=%d serving=%s\n",
                $row['state'],
                $row['name'],
                $row['local_count'],
                $row['serving'] ? 'yes' : 'no'
            );
        }
    }
    echo "\nTip: php scripts/coverage-report.php --thin 200\n";
    echo "After widening OSM selectors: node tools/osm-import.js && php scripts/seed.php --osm\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Coverage report failed: ' . $e->getMessage() . "\n");
    exit(1);
}
