<?php

declare(strict_types=1);

/**
 * Offline smoke test for COM-005 legal PDF pack (no PHPUnit/vendor required).
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
use App\Services\Legal\SimplePdfBuilder;

$failures = 0;
$assert = static function (bool $cond, string $message) use (&$failures): void {
    if ($cond) {
        echo "OK  {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL  {$message}\n";
};

$catalog = new LegalDocumentCatalog();
$all = $catalog->all();
$assert(count($all) === 6, 'catalog has 6 documents');
$assert($catalog->find('privacy-policy') !== null, 'privacy-policy exists');
$assert($catalog->find('nope') === null, 'unknown id returns null');
$assert(!str_contains($catalog->markdownFor('terms-of-use'), 'Draft honesty notes'), 'honesty notes stripped');

$pdf = new SimplePdfBuilder();
$pdf->setRunningHeader('Assist', 'Test');
$pdf->setFooterCenter('Footer');
$pdf->addTitlePage('E', 'T', 'S', 'M', 'D');
$pdf->heading('H', 1);
$pdf->paragraph('Body text for wrap coverage ' . str_repeat('word ', 40));
$pdf->bullet('Bullet');
$pdf->table([['A', 'B'], ['1', '2']]);
$binary = $pdf->output();
$assert(str_starts_with($binary, '%PDF-1.4'), 'builder PDF header');
$assert(str_contains($binary, '%%EOF'), 'builder PDF eof');

$renderer = new LegalPdfRenderer($catalog);
foreach ($all as $doc) {
    $b = $renderer->render((string) $doc['id']);
    $assert(str_starts_with($b, '%PDF-1.4') && strlen($b) > 2000, 'render ' . $doc['id']);
}

exit($failures === 0 ? 0 : 1);
