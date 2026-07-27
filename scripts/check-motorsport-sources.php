<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\MotorsportSourceMonitor;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');
$limit = isset($argv[1]) ? max(1, min(100, (int) $argv[1])) : 20;

try {
    echo json_encode((new MotorsportSourceMonitor())->checkDue($limit), JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, 'Motorsport source check failed: ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
