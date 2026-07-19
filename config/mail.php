<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'driver'       => Env::get('MAIL_DRIVER', 'smtp'),
    'host'         => Env::get('MAIL_HOST', ''),
    'port'         => (int) Env::get('MAIL_PORT', 587),
    'username'     => Env::get('MAIL_USERNAME', ''),
    'password'     => Env::get('MAIL_PASSWORD', ''),
    'encryption'   => Env::get('MAIL_ENCRYPTION', 'tls'),
    'from_address' => Env::get('MAIL_FROM_ADDRESS', ''),
    'from_name'    => Env::get('MAIL_FROM_NAME', 'VanAssist'),
    'max_attempts' => (int) Env::get('MAIL_MAX_ATTEMPTS', 3),
];
