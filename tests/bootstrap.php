<?php

declare(strict_types=1);

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;

if (getenv('RUN_INTEGRATION_TESTS') === '1') {
    Env::load(BASE_PATH . '/.env');
    Config::load(BASE_PATH . '/config');
} else {
    // Minimal config so unit tests work without a database or real .env.
    Config::set('app.url', 'http://localhost');
    Config::set('app.name', 'VanAssist');
    Config::set('app.debug', true);
}
