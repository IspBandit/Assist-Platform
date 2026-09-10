<?php

declare(strict_types=1);

use App\Core\Config;
use App\Helpers\Env;
use App\Services\RoadDistance\GoogleRoutesCredentialProvisioner;

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Forbidden\n");
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$arguments = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
if (!in_array('--stdin', $arguments, true)) {
    fwrite(STDERR, "Pass the protected credential through standard input.\n");
    exit(2);
}

$credential = stream_get_contents(STDIN, 512);
if (!is_string($credential)) {
    fwrite(STDERR, "Unable to read the protected credential.\n");
    exit(1);
}

try {
    (new GoogleRoutesCredentialProvisioner())->provision($credential);
    fwrite(STDOUT, "Google Routes credential encrypted and provisioned.\n");
} catch (Throwable $error) {
    fwrite(STDERR, "Google Routes credential provisioning failed: " . $error->getMessage() . "\n");
    exit(1);
}
