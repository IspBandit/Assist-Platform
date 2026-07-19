<?php

declare(strict_types=1);

/**
 * VanAssist cron entrypoint. Usage:
 *   php /home/CPANEL_USERNAME/vanassist/cron/run.php <task_key>
 *
 * Example cPanel cron commands are documented in INSTALL-CPANEL.md.
 * Each task acquires an exclusive lock to prevent overlapping execution.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\CronRunner;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
date_default_timezone_set((string) Config::get('app.timezone', 'Australia/Brisbane'));

$task = $argv[1] ?? '';
if ($task === '') {
    fwrite(STDERR, "Usage: php cron/run.php <task_key>\n");
    exit(2);
}

if (!is_file(BASE_PATH . '/storage/installed.lock')) {
    fwrite(STDERR, "VanAssist is not installed yet. Cron aborted.\n");
    exit(0);
}

exit((new CronRunner())->run($task));
