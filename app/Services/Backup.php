<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Logger;

/**
 * Creates compressed database backups in /storage/backups and applies
 * retention. Prefers the MariaDB dump client when available; otherwise falls back to a
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

        $usedNativeDump = $this->nativeDump($cfg, $file);
        if (!$usedNativeDump) {
            $this->phpDump($file);
        }

        if (!is_file($file) || filesize($file) === 0) {
            throw new \RuntimeException('Database backup did not produce a non-empty SQL file.');
        }

        // Compress if gzip is available.
        if (function_exists('gzopen')) {
            $compressed = $this->gzipFile($file);
            if ($compressed) {
                if (!unlink($file)) {
                    @unlink($file . '.gz');
                    throw new \RuntimeException('Database backup was compressed but the source SQL file could not be removed.');
                }
                $file .= '.gz';
            }
        }

        @chmod($file, 0600);
        $hash = hash_file('sha256', $file);
        if (!is_string($hash) || $hash === '') {
            throw new \RuntimeException('Database backup checksum could not be calculated.');
        }
        $manifest = $file . '.sha256';
        if (file_put_contents($manifest, $hash . '  ' . basename($file) . PHP_EOL, LOCK_EX) === false) {
            throw new \RuntimeException('Database backup checksum manifest could not be written.');
        }
        @chmod($manifest, 0600);

        $this->applyRetention($dir);

        Logger::info('Database backup created.', ['file' => basename($file), 'native_dump' => $usedNativeDump], 'backup');
        return ['file' => basename($file), 'method' => $usedNativeDump ? 'mariadb-dump' : 'php'];
    }

    private function nativeDump(array $cfg, string $file): bool
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
                'mariadb-dump --defaults-extra-file=%s --single-transaction --routines --triggers %s > %s 2>/dev/null',
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
            throw new \RuntimeException('Unable to create the database backup file.');
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

    private function gzipFile(string $file): bool
    {
        $in = fopen($file, 'rb');
        $out = gzopen($file . '.gz', 'wb9');
        if ($in === false || $out === false) {
            if (is_resource($in)) {
                fclose($in);
            }
            if ($out !== false) {
                gzclose($out);
            }
            @unlink($file . '.gz');
            return false;
        }
        while (!feof($in)) {
            $chunk = fread($in, 1 << 18);
            if ($chunk === false || gzwrite($out, $chunk) === false) {
                fclose($in);
                gzclose($out);
                @unlink($file . '.gz');
                return false;
            }
        }
        fclose($in);
        gzclose($out);
        return is_file($file . '.gz') && filesize($file . '.gz') > 0;
    }

    private function applyRetention(string $dir): void
    {
        $keepDaily = (int) config('backups.retention.daily', 7);
        $files = array_values(array_filter(
            glob($dir . '/db_*.sql*') ?: [],
            static fn (string $path): bool => !str_ends_with($path, '.sha256')
        ));
        rsort($files);
        foreach (array_slice($files, $keepDaily) as $old) {
            @unlink($old);
            @unlink($old . '.sha256');
        }
    }
}
