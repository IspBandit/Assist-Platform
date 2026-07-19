<?php

declare(strict_types=1);

/**
 * PSR-4 autoloader for the "App\" namespace mapped to /app.
 *
 * This works WITHOUT running `composer install`, so the platform boots on
 * plain cPanel hosting. If a Composer vendor autoloader exists (e.g. for
 * PHPMailer), it is loaded as well.
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = BASE_PATH . '/app/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Composer dependencies (PHPMailer, PHPUnit, optional libs) when available.
$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require $composerAutoload;
}

// Global helper functions.
require BASE_PATH . '/app/Helpers/functions.php';
