<?php

declare(strict_types=1);

use App\Core\Config;
use App\Helpers\Env;
use App\Services\SecretCipher;
use App\Services\Settings;

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$arguments = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];
$validateOnly = in_array('--validate-only', $arguments, true);
$stored = (string) Settings::get('mail_password', '');

if ($stored === '') {
    fwrite(STDOUT, "No database SMTP password is configured.\n");
    exit(0);
}

if (SecretCipher::encrypted($stored)) {
    SecretCipher::decrypt($stored);
    fwrite(STDOUT, "Database SMTP password is encrypted and decryptable.\n");
    exit(0);
}

if ($validateOnly) {
    fwrite(STDERR, "Database SMTP password is still stored as plaintext.\n");
    exit(1);
}

Settings::set('mail_password', SecretCipher::encrypt($stored));
fwrite(STDOUT, "Database SMTP password encrypted successfully. Back up APP_KEY securely; rotation requires re-encryption.\n");
