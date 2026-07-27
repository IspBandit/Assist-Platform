<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\RegulatorySourceMonitor;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$limit = 20;
$arguments = isset($_SERVER['argv']) && is_array($_SERVER['argv']) ? $_SERVER['argv'] : [];
foreach (array_slice($arguments, 1) as $argument) {
    if (str_starts_with($argument, '--limit=')) {
        $limit = (int) substr($argument, 8);
    }
}

try {
    $summary = (new RegulatorySourceMonitor())->checkDue($limit);
    echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit($summary['failed'] > 0 ? 2 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Regulatory source check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
