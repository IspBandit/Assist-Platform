<?php

declare(strict_types=1);

/**
 * Router for PHP's local development server. Real deployments use Caddy or
 * Apache; this keeps static assets out of the front controller during audits.
 */
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$publicRoot = realpath(dirname(__DIR__) . '/public');
$publicFile = realpath(dirname(__DIR__) . '/public' . (is_string($path) ? $path : '/'));
if (
    is_string($publicRoot)
    && is_string($publicFile)
    && str_starts_with($publicFile, $publicRoot . DIRECTORY_SEPARATOR)
    && is_file($publicFile)
) {
    if ($path === '/service-worker.js') {
        header('Content-Type: application/javascript; charset=UTF-8');
        header('Service-Worker-Allowed: /');
        readfile($publicFile);
        return true;
    }
    return false;
}

require dirname(__DIR__) . '/public/index.php';
