<?php

declare(strict_types=1);

/**
 * PSR-4 autoloader for the "App\" namespace mapped to /app.
 *
 * Prefer Composer when vendor/ is present (local/dev/CI). Fall back to the
 * lightweight loader so the platform still boots on hosting without
 * `composer install`. Never register both for App\ — dual loaders redeclare
 * classes under PHPUnit and some PHP versions.
 */

$composerAutoload = BASE_PATH . '/vendor/autoload.php';
if (!class_exists(Composer\Autoload\ClassLoader::class, false) && is_file($composerAutoload)) {
    require $composerAutoload;
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        $baseDir = BASE_PATH . '/app/';

        if (!str_starts_with($class, $prefix)) {
            return;
        }

        $relative = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($file)) {
            require_once $file;
        }
    });
}

// Global helper functions.
require_once BASE_PATH . '/app/Helpers/functions.php';
