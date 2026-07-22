<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { fwrite(STDERR, "CLI only.\n"); exit(1); }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\BrandProviderEnrichmentService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
$arguments = is_array($_SERVER['argv'] ?? null) ? $_SERVER['argv'] : [];
$dryRun = in_array('--dry-run', $arguments, true);
try {
    $counts = (new BrandProviderEnrichmentService())->run($dryRun);
    echo ($dryRun ? "Dry-run scan complete:\n" : "Brand provider enrichment complete:\n");
    foreach ($counts as $label => $count) { echo '  ' . $label . ': ' . $count . "\n"; }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Brand provider enrichment failed: ' . $e->getMessage() . "\n");
    exit(1);
}
