<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'host'    => Env::get('DB_HOST', 'localhost'),
    'port'    => (int) Env::get('DB_PORT', 3306),
    'name'    => Env::get('DB_NAME', ''),
    'user'    => Env::get('DB_USER', ''),
    'password' => Env::get('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'options' => [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_STRINGIFY_FETCHES  => false,
    ],
];
