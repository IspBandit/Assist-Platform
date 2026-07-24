<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

App\Helpers\Env::load(BASE_PATH . '/.env');
App\Core\Config::load(BASE_PATH . '/config');

$result=(new App\Services\DataSourceService())->runDueSchedules();
fwrite(STDOUT,json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES).PHP_EOL);
