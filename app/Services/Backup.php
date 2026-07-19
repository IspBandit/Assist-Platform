<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Creates compressed database backups in /storage/backups and applies
 * retention. Prefers mysqldump when available; otherwise falls back to a
 * PHP-based SQL export suitable for shared hosting.
 */
final class Backup
{
    public function run(): array
    {
        $dir = base_path('storage/backups');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        $cfg = config('database');
        $stamp = date('Ymd_His');
        $file = $dir . '/db_' . $stamp . '.sql';

        $usedMysqldump = $this->mysqldump($cfg, $file);
        if (!$usedMysqldump) {
            $this->phpDump($file);
        }

        // Compress if gzip is available.
        if (function_exists('gzopen') && is_file($file)) {
            $this->gzipFile($file);
            @unlink($file);
            $file .= '.gz';
        }

        $this->applyRetention($dir);

        Logger::info('Database backup created.', ['file' => basename($file), 'mysqldump' => $usedMysqldump], 'backup');
        return ['file' => basename($file), 'method' => $usedMysqldump ? 'mysqldump' : 'php'];
    }

    private function mysqldump(array $cfg, string $file): bool
    {
        if (!function_exists('exec')) {
            return false;
        }
        $disabled = explode(',', (string) ini_get('disable_functions'));
        if (in_array('exec', array_map('trim', $disabled), true)) {
            return false;
        }

        $cacheDir = base_path('storage/cache');
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0770, true) && !is_dir($cacheDir)) {
            return false;
        }
        $credentialFile = tempnam($cacheDir, 'mysql_');
        if ($credentialFile === false) {
            return false;
        }

        try {
            chmod($credentialFile, 0600);
            $options = "[client]\n"
                . 'host="' . $this->optionValue((string) $cfg['host']) . "\"\n"
                . 'port=' . (int) $cfg['port'] . "\n"
                . 'user="' . $this->optionValue((string) $cfg['user']) . "\"\n"
                . 'password="' . $this->optionValue((string) $cfg['password']) . "\"\n";
            if (file_put_contents($credentialFile, $options, LOCK_EX) === false) {
                return false;
            }

            $cmd = sprintf(
                'mysqldump --defaults-extra-file=%s --no-tablespaces %s > %s 2>/dev/null',
                escapeshellarg($credentialFile),
                escapeshellarg((string) $cfg['name']),
                escapeshellarg($file)
            );
            exec($cmd, $out, $code);
            return $code === 0 && is_file($file) && filesize($file) > 0;
        } finally {
            @unlink($credentialFile);
        }
    }

    private function optionValue(string $value): string
    {
        if (str_contains($value, "\n") || str_contains($value, "\r")) {
            throw new \RuntimeException('Database credentials contain invalid control characters');
        }

        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }

    private function phpDump(string $file): void
    {
        $pdo = Database::connection();
        $fh = fopen($file, 'w');
        if ($fh === false) {
            return;
        }
        fwrite($fh, "-- VanAssist PHP backup " . date('c') . "\nSET FOREIGN_KEY_CHECKS=0;\n");

        $tables = array_column(Database::select('SHOW TABLES'), 0) ?: [];
        if ($tables === []) {
            // SHOW TABLES returns assoc with dynamic key; fetch column 0 instead.
            foreach (Database::select('SHOW TABLES') as $row) {
                $tables[] = array_values($row)[0];
            }
        }

        foreach ($tables as $table) {
            $create = Database::selectOne("SHOW CREATE TABLE `{$table}`");
            $createSql = $create['Create Table'] ?? ($create['Create View'] ?? null);
            if ($createSql !== null) {
                fwrite($fh, "\nDROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n");
            }
            foreach (Database::select("SELECT * FROM `{$table}`") as $rowData) {
                $cols = array_map(static fn ($c) => "`{$c}`", array_keys($rowData));
                $vals = array_map(static fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($rowData));
                fwrite($fh, "INSERT INTO `{$table}` (" . implode(',', $cols) . ') VALUES (' . implode(',', $vals) . ");\n");
            }
        }
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);
    }

    private function gzipFile(string $file): void
    {
        $in = fopen($file, 'rb');
        $out = gzopen($file . '.gz', 'wb9');
        if ($in === false || $out === false) {
            return;
        }
        while (!feof($in)) {
            gzwrite($out, (string) fread($in, 1 << 18));
        }
        fclose($in);
        gzclose($out);
    }

    private function applyRetention(string $dir): void
    {
        $keepDaily = (int) config('backups.retention.daily', 7);
        $files = glob($dir . '/db_*.sql*') ?: [];
        rsort($files);
        foreach (array_slice($files, $keepDaily) as $old) {
            @unlink($old);
        }
    }
}
