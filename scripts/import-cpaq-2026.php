<?php

declare(strict_types=1);

/**
 * Import authorised CPAQ Explore Queensland Caravan Parks Directory 2026.
 *
 * Permission: verbal grant from Vee (CPAQ) to Glen for parks and services.
 * Seed JSON: database/seeds/cpaq-2026/{parks,trade}.json
 *
 * Usage:
 *   php scripts/import-cpaq-2026.php              # dry-run (rolled back)
 *   php scripts/import-cpaq-2026.php --apply      # commit
 *   php scripts/import-cpaq-2026.php --parks-only
 *   php scripts/import-cpaq-2026.php --trade-only --apply
 *
 * Extract first: python tools/cpaq/extract_cpaq_directory.py
 * Docs: docs/CPAQ_2026_IMPORT.md (DATA-001 / VAN-001)
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Helpers\Env;
use App\Services\AuditLog;
use App\Services\Cpaq2026ImportService;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$arguments = isset($_SERVER['argv']) && is_array($_SERVER['argv'])
    ? array_values(array_filter($_SERVER['argv'], 'is_string'))
    : [];

$apply = in_array('--apply', $arguments, true);
$parksOnly = in_array('--parks-only', $arguments, true);
$tradeOnly = in_array('--trade-only', $arguments, true);

if ($parksOnly && $tradeOnly) {
    fwrite(STDERR, "Use only one of --parks-only or --trade-only.\n");
    exit(1);
}

try {
    $summary = (new Cpaq2026ImportService())->import(
        $apply,
        !$tradeOnly,
        !$parksOnly
    );
} catch (Throwable $exception) {
    fwrite(STDERR, 'CPAQ import failed: ' . $exception->getMessage() . "\n");
    exit(1);
}

if ($apply) {
    AuditLog::record(
        'cpaq_2026.import_applied',
        'cpaq_import',
        null,
        null,
        json_encode([
            'parks' => $summary['parks'] ?? [],
            'trade' => $summary['trade'] ?? [],
            'mode' => $summary['mode'] ?? 'applied',
        ], JSON_UNESCAPED_SLASHES)
    );
}

echo json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
exit(0);
