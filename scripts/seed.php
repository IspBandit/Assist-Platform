<?php

declare(strict_types=1);

/**
 * CLI seeder. Usage:
 *   php scripts/seed.php              # core seed data (roles, locations, categories, content)
 *   php scripts/seed.php --demo       # also insert demo records
 *   php scripts/seed.php --national   # researched national_import.json only
 *   php scripts/seed.php --towns      # national towns/suburbs only
 *   php scripts/seed.php --osm        # OpenStreetMap businesses (loops until done)
 *   php scripts/seed.php --locality   # locality research matrix (loops until done)
 *   php scripts/seed.php --providers  # towns + national + osm + locality + feature cities
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\DemoSeeder;
use App\Services\MajorCityCoverageService;
use App\Services\NationalImportSeeder;
use App\Services\ProviderImportRunner;
use App\Services\Seeder;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

/** @var array<int,string> $arguments */
$arguments = $_SERVER['argv'] ?? [];

$progress = static function (array $r): void {
    $next = (int) ($r['next'] ?? -1);
    $total = (int) ($r['total'] ?? 0);
    $done = $next < 0 ? $total : $next;
    echo sprintf(
        "  … %s / %s ( +%d new, +%d enriched)\n",
        number_format($done),
        number_format($total),
        (int) ($r['providers'] ?? 0),
        (int) ($r['providers_enriched'] ?? 0)
    );
};

try {
    $runner = new ProviderImportRunner();

    if (in_array('--national', $arguments, true)) {
        $summary = (new NationalImportSeeder())->seed();
        echo 'National import: ' . json_encode($summary) . "\n";
        exit(0);
    }

    if (in_array('--towns', $arguments, true)) {
        $summary = $runner->seedTowns();
        echo 'Towns: ' . json_encode($summary) . "\n";
        exit(isset($summary['error']) ? 1 : 0);
    }

    if (in_array('--osm', $arguments, true)) {
        echo "OpenStreetMap import (to completion)…\n";
        $summary = $runner->runOsmToCompletion($progress);
        echo 'OSM: ' . json_encode($summary) . "\n";
        exit(isset($summary['error']) ? 1 : 0);
    }

    if (in_array('--locality', $arguments, true)) {
        echo "Locality-provider import (to completion)…\n";
        $summary = $runner->runLocalityToCompletion($progress);
        echo 'Locality: ' . json_encode($summary) . "\n";
        exit(isset($summary['error']) ? 1 : 0);
    }

    if (in_array('--providers', $arguments, true)) {
        echo "1/5 Towns…\n";
        $t = $runner->seedTowns();
        echo '  ' . json_encode($t) . "\n";
        if (isset($t['error'])) {
            exit(1);
        }

        echo "2/5 National researched import…\n";
        $n = (new NationalImportSeeder())->seed();
        echo '  ' . json_encode($n) . "\n";
        if (isset($n['error'])) {
            exit(1);
        }

        echo "3/5 OpenStreetMap…\n";
        $o = $runner->runOsmToCompletion($progress);
        echo '  ' . json_encode($o) . "\n";
        if (isset($o['error'])) {
            exit(1);
        }

        echo "4/5 Locality research…\n";
        $l = $runner->runLocalityToCompletion($progress);
        echo '  ' . json_encode($l) . "\n";
        if (isset($l['error'])) {
            $err = (string) $l['error'];
            if (!str_contains($err, 'not found') && !str_contains($err, 'empty')) {
                exit(1);
            }
            echo "  (skipped — no locality seed)\n";
        }

        echo "5/5 Promote major cities…\n";
        $f = MajorCityCoverageService::featureMajorCityTowns();
        echo "  featured={$f}\n";
        echo "Provider refresh complete.\n";
        exit(0);
    }

    (new Seeder())->seedAll();
    echo "Core seed data applied.\n";

    if (in_array('--demo', $arguments, true)) {
        (new DemoSeeder())->seed();
        echo "Demo data applied.\n";
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Seeding failed: ' . $e->getMessage() . "\n");
    exit(1);
}
