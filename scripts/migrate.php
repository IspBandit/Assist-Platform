<?php

declare(strict_types=1);

/**
 * CLI migration runner. Usage (from project root):
 *   php scripts/migrate.php
 *
 * Works under cPanel "Setup Cron Job" or via SSH. Also callable in the browser
 * only through the installation wizard (never expose this script publicly).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\Migrator;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

try {
    $migrator = new Migrator();
    $ran = $migrator->run();
    if ($ran === []) {
        echo "Nothing to migrate. Database is up to date.\n";
    } else {
        echo "Applied migrations:\n";
        foreach ($ran as $name) {
            echo "  - {$name}\n";
        }
    }
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'Migration failed: ' . $e->getMessage() . "\n");
    if ((string) Env::get('APP_ENV', 'production') !== 'production' && $e->getPrevious() !== null) {
        fwrite(STDERR, 'Cause: ' . $e->getPrevious()->getMessage() . "\n");
    }
    exit(1);
}
