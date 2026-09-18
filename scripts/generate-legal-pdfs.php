<?php

declare(strict_types=1);

/**
 * Offline generator for COM-005 legal PDF pack (no Composer required).
 * Usage: php scripts/generate-legal-pdfs.php
 */

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $path = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($path)) {
        require $path;
    }
});

if (!defined('BASE_PATH')) {
    define('BASE_PATH', $root);
}

require $root . '/app/Helpers/functions.php';

use App\Services\Legal\LegalDocumentCatalog;
use App\Services\Legal\LegalPdfRenderer;

$outDir = $root . '/storage/legal';
if (!is_dir($outDir) && !mkdir($outDir, 0775, true) && !is_dir($outDir)) {
    fwrite(STDERR, "Unable to create {$outDir}\n");
    exit(1);
}

$catalog = new LegalDocumentCatalog();
$renderer = new LegalPdfRenderer($catalog);

foreach ($catalog->all() as $doc) {
    $binary = $renderer->render((string) $doc['id']);
    if (!str_starts_with($binary, '%PDF-')) {
        fwrite(STDERR, "Invalid PDF for {$doc['id']}\n");
        exit(1);
    }
    $path = $outDir . DIRECTORY_SEPARATOR . $doc['filename'];
    file_put_contents($path, $binary);
    echo $doc['id'] . ' ' . strlen($binary) . ' bytes -> ' . $path . PHP_EOL;
}

echo "OK\n";
