<?php

declare(strict_types=1);

/**
 * Restartable Assist Platform data backfill.
 *
 * Usage:
 *   php scripts/backfill-platform.php --validate-only
 *   php scripts/backfill-platform.php --batch=500
 *
 * Run validation before and after. This script is never invoked automatically
 * by deployment or schema migration.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\PlatformBackfill;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$options = getopt('', ['batch:', 'validate-only']);
$batchSize = isset($options['batch']) ? (int) $options['batch'] : 500;
$validateOnly = array_key_exists('validate-only', $options);

try {
    $backfill = new PlatformBackfill();
    if (!$validateOnly) {
        echo "Running additive platform backfill in batches of {$batchSize}...\n";
        foreach ($backfill->run($batchSize) as $name => $count) {
            echo "  {$name}: {$count} rows inserted\n";
        }
    }

    echo "Integrity checks:\n";
    $valid = true;
    foreach ($backfill->validate() as $name => $check) {
        $status = $check['valid'] ? 'OK' : 'FAILED';
        echo "  {$name}: {$status} ({$check['actual']}/{$check['expected']})\n";
        $valid = $valid && $check['valid'];
    }

    exit($valid ? 0 : 2);
} catch (Throwable $e) {
    fwrite(STDERR, 'Platform backfill failed: ' . $e->getMessage() . "\n");
    exit(1);
}
