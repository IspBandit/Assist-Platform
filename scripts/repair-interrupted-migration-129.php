<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { exit(1); }
$configuredRoot = getenv('ASSIST_APP_ROOT');
define('BASE_PATH', is_string($configuredRoot) && $configuredRoot !== '' ? $configuredRoot : dirname(__DIR__));
require BASE_PATH . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Database;
use App\Helpers\Env;

Env::load(BASE_PATH . '/.env');
Config::load(BASE_PATH . '/config');

$name = '129_merge_duplicate_stays.sql';
$row = Database::selectOne('SELECT status FROM migrations WHERE migration = ? LIMIT 1', [$name]);
if ($row === null) {
    echo "Migration 129 has no interrupted history row. Nothing to repair.\n";
    exit(0);
}
if (!in_array((string) $row['status'], ['running', 'failed'], true)) {
    fwrite(STDERR, "Refusing to alter migration 129 with status {$row['status']}.\n");
    exit(2);
}
if (!Database::tableExists('caravan_park_source_aliases')) {
    fwrite(STDERR, "Refusing repair: migration 129 did not create its additive alias table.\n");
    exit(2);
}
$locked = (int) Database::scalar("SELECT IS_FREE_LOCK('assist_platform_schema_migrations')");
if ($locked !== 1) {
    fwrite(STDERR, "Refusing repair while the migration advisory lock is held.\n");
    exit(2);
}
Database::query('DELETE FROM migrations WHERE migration = ? AND status IN (?, ?)', [$name, 'running', 'failed']);
echo "Cleared the interrupted migration 129 history row for a controlled retry.\n";
