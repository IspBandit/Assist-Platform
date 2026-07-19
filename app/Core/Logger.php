<?php

declare(strict_types=1);

namespace App\Core;

use App\Platform\Brand\BrandContext;
use App\Platform\Support\RequestContext;
use Throwable;

/**
 * Structured logging to storage/logs/*.log, with a database fallback when the
 * log directory is missing or not writable (common on cPanel shared hosting).
 */
final class Logger
{
    private static ?string $lastError = null;
    private static ?bool $dbTableExists = null;

    public static function log(string $level, string $message, array $context = [], string $channel = 'app'): void
    {
        if (RequestContext::hasRequestId()) {
            $context['request_id'] = RequestContext::requestId();
        }
        if (BrandContext::hasCurrent()) {
            $context['brand'] = BrandContext::current()->id();
        }

        $entry = sprintf(
            "[%s] %s.%s: %s %s\n",
            date('Y-m-d H:i:s'),
            $channel,
            strtoupper($level),
            $message,
            $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES) : ''
        );

        if (!self::writeFile($channel, $entry)) {
            self::writeDb($channel, $level, $message, $context);
        }
    }

    public static function error(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('error', $message, $context, $channel);
    }

    public static function info(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('info', $message, $context, $channel);
    }

    public static function warning(string $message, array $context = [], string $channel = 'app'): void
    {
        self::log('warning', $message, $context, $channel);
    }

    /** @return array{path:string,exists:bool,writable:bool,last_error:?string,db_table:bool,db_count:int} */
    public static function diagnostics(): array
    {
        $dir = self::logDir();
        $exists = is_dir($dir);
        $writable = $exists && is_writable($dir);

        $dbCount = 0;
        $dbTable = self::dbTableExists();
        if ($dbTable) {
            try {
                $dbCount = (int) Database::scalar('SELECT COUNT(*) FROM system_logs');
            } catch (Throwable) {
                $dbTable = false;
            }
        }

        return [
            'path'       => $dir,
            'exists'     => $exists,
            'writable'   => $writable,
            'last_error' => self::$lastError,
            'db_table'   => $dbTable,
            'db_count'   => $dbCount,
        ];
    }

    /** Create log directories and verify we can write a line. */
    public static function repair(): array
    {
        $dir = self::ensureLogDir();
        $ok = self::writeFile('_repair', '[' . date('Y-m-d H:i:s') . "] app.INFO: Log directory repair test\n");
        if ($ok) {
            @unlink($dir . '/_repair.log');
        }
        self::info('Log repair check from admin.', [], 'app');
        return self::diagnostics();
    }

    /**
     * @return array<int,array{line:string,created_at:string,source:string}>
     */
    public static function tail(string $channel, int $maxLines = 300): array
    {
        $maxLines = max(1, min(2000, $maxLines));
        $lines = [];

        $file = self::logDir() . '/' . $channel . '.log';
        if (is_file($file) && is_readable($file)) {
            foreach (self::tailFile($file, $maxLines) as $line) {
                if (trim($line) !== '') {
                    $lines[] = ['line' => $line, 'created_at' => '', 'source' => 'file'];
                }
            }
        }

        if (count($lines) < $maxLines && self::dbTableExists()) {
            try {
                $rows = Database::select(
                    'SELECT level, message, context_json, created_at FROM system_logs '
                    . 'WHERE channel = ? ORDER BY id DESC LIMIT ' . ($maxLines - count($lines)),
                    [$channel]
                );
                foreach (array_reverse($rows) as $row) {
                    $ctx = $row['context_json'] ?? '';
                    $line = sprintf(
                        '[%s] %s.%s: %s %s',
                        $row['created_at'],
                        $channel,
                        strtoupper((string) $row['level']),
                        $row['message'],
                        $ctx !== '' && $ctx !== null ? $ctx : ''
                    );
                    $lines[] = ['line' => $line, 'created_at' => (string) $row['created_at'], 'source' => 'database'];
                }
            } catch (Throwable) {
                // ignore
            }
        }

        return array_slice($lines, -$maxLines);
    }

    /** @return string[] */
    public static function channels(): array
    {
        $known = ['email', 'errors', 'app', 'content', 'cron', 'payments', 'backup'];
        $found = [];
        $dir = self::logDir();
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.log') ?: [] as $file) {
                $found[] = basename($file, '.log');
            }
        }
        if (self::dbTableExists()) {
            try {
                foreach (Database::select('SELECT DISTINCT channel FROM system_logs ORDER BY channel') as $row) {
                    $found[] = (string) $row['channel'];
                }
            } catch (Throwable) {
                // ignore
            }
        }
        $channels = array_values(array_unique(array_merge($known, $found)));
        sort($channels);
        return $channels;
    }

    private static function logDir(): string
    {
        return base_path('storage/logs');
    }

    private static function ensureLogDir(): string
    {
        $dir = self::logDir();
        if (!is_dir($dir)) {
            if (!@mkdir($dir, 0775, true) && !is_dir($dir)) {
                self::$lastError = 'Could not create directory: ' . $dir;
            }
        }
        if (is_dir($dir) && !is_writable($dir)) {
            @chmod($dir, 0775);
        }
        return $dir;
    }

    private static function writeFile(string $channel, string $entry): bool
    {
        $dir = self::ensureLogDir();
        if (!is_dir($dir) || !is_writable($dir)) {
            self::$lastError = 'Log directory not writable: ' . $dir;
            return false;
        }

        $file = $dir . '/' . preg_replace('/[^a-z0-9_\-]/i', '', $channel) . '.log';
        $bytes = @file_put_contents($file, $entry, FILE_APPEND | LOCK_EX);
        if ($bytes === false) {
            self::$lastError = 'file_put_contents failed for ' . $file
                . (function_exists('error_get_last') && ($e = error_get_last()) ? ': ' . ($e['message'] ?? '') : '');
            return false;
        }

        self::$lastError = null;
        return true;
    }

    private static function writeDb(string $channel, string $level, string $message, array $context): void
    {
        if (!self::dbTableExists()) {
            return;
        }
        try {
            Database::query(
                'INSERT INTO system_logs (channel, level, message, context_json, created_at) VALUES (?, ?, ?, ?, NOW())',
                [
                    substr($channel, 0, 40),
                    substr(strtoupper($level), 0, 20),
                    substr($message, 0, 500),
                    $context !== [] ? json_encode($context, JSON_UNESCAPED_SLASHES) : null,
                ]
            );
        } catch (Throwable) {
            // Logging must never break the request.
        }
    }

    private static function dbTableExists(): bool
    {
        if (self::$dbTableExists !== null) {
            return self::$dbTableExists;
        }
        try {
            self::$dbTableExists = Database::tableExists('system_logs');
        } catch (Throwable) {
            self::$dbTableExists = false;
        }
        return self::$dbTableExists;
    }

    /** @return string[] */
    private static function tailFile(string $file, int $maxLines): array
    {
        $handle = @fopen($file, 'rb');
        if ($handle === false) {
            return [];
        }

        $buffer = '';
        $chunkSize = 8192;
        $pos = (int) filesize($file);
        $newlines = 0;

        while ($pos > 0 && $newlines <= $maxLines) {
            $read = (int) min($chunkSize, $pos);
            $pos -= $read;
            fseek($handle, $pos);
            $buffer = (string) fread($handle, $read) . $buffer;
            $newlines = substr_count($buffer, "\n");
        }
        fclose($handle);

        $allLines = explode("\n", rtrim($buffer, "\n"));
        return array_slice($allLines, -$maxLines);
    }
}
