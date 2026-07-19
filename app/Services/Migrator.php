<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;
use Throwable;

/**
 * Runs SQL migration files from /database/migrations in filename order,
 * tracking applied migrations in a "migrations" table so they run once.
 *
 * Migration files contain plain DDL/DML statements separated by ";".
 * Comment lines starting with "--" are ignored.
 */
final class Migrator
{
    private const LOCK_NAME = 'assist_platform_schema_migrations';
    private const LOCK_TIMEOUT_SECONDS = 10;

    private string $path;

    public function __construct(?string $path = null)
    {
        $this->path = $path ?? base_path('database/migrations');
    }

    public function ensureMigrationsTable(): void
    {
        Database::connection()->exec(
            'CREATE TABLE IF NOT EXISTS migrations ('
            . 'id INT UNSIGNED NOT NULL AUTO_INCREMENT,'
            . 'migration VARCHAR(190) NOT NULL,'
            . 'batch INT NOT NULL DEFAULT 1,'
            . "status VARCHAR(20) NOT NULL DEFAULT 'succeeded',"
            . 'checksum CHAR(64) NULL,'
            . 'started_at DATETIME NULL,'
            . 'completed_at DATETIME NULL,'
            . 'error_text TEXT NULL,'
            . 'ran_at DATETIME NULL,'
            . 'PRIMARY KEY (id), UNIQUE KEY uq_migration (migration)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Upgrade migration history created by earlier VanAssist releases.
        $this->ensureColumn('status', "VARCHAR(20) NOT NULL DEFAULT 'succeeded' AFTER batch");
        $this->ensureColumn('checksum', 'CHAR(64) NULL AFTER status');
        $this->ensureColumn('started_at', 'DATETIME NULL AFTER checksum');
        $this->ensureColumn('completed_at', 'DATETIME NULL AFTER started_at');
        $this->ensureColumn('error_text', 'TEXT NULL AFTER completed_at');
    }

    /** @return array<int,string> list of migration filenames that were applied */
    public function run(): array
    {
        $this->ensureMigrationsTable();
        $this->acquireLock();

        try {
            return $this->runLocked();
        } finally {
            $this->releaseLock();
        }
    }

    /** @return array<int,string> */
    private function runLocked(): array
    {
        $history = [];
        foreach (Database::select('SELECT migration, status, checksum FROM migrations') as $row) {
            $history[(string) $row['migration']] = $row;
        }

        $batch = (int) Database::scalar('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations');
        $files = glob($this->path . '/*.sql') ?: [];
        sort($files);

        $ran = [];
        foreach ($files as $file) {
            $name = basename($file);
            $checksum = hash_file('sha256', $file);
            if ($checksum === false) {
                throw new RuntimeException("Unable to checksum migration {$name}");
            }

            if (isset($history[$name])) {
                $this->validateAppliedMigration($name, $checksum, $history[$name]);
                continue;
            }

            Database::query(
                "INSERT INTO migrations (migration, batch, status, checksum, started_at) VALUES (?, ?, 'running', ?, NOW())",
                [$name, $batch, $checksum]
            );

            try {
                $this->runFile($file);
                Database::query(
                    "UPDATE migrations SET status = 'succeeded', ran_at = NOW(), completed_at = NOW(), error_text = NULL WHERE migration = ?",
                    [$name]
                );
                $ran[] = $name;
            } catch (Throwable $e) {
                Database::query(
                    "UPDATE migrations SET status = 'failed', completed_at = NOW(), error_text = ? WHERE migration = ?",
                    [substr($e->getMessage(), 0, 65000), $name]
                );
                throw new RuntimeException(
                    "Migration {$name} failed and is marked dirty. Repair it before retrying.",
                    0,
                    $e
                );
            }
        }

        return $ran;
    }

    /** @param array<string,mixed> $history */
    private function validateAppliedMigration(string $name, string $checksum, array $history): void
    {
        $status = (string) ($history['status'] ?? 'succeeded');
        if ($status !== 'succeeded') {
            throw new RuntimeException(
                "Migration {$name} is marked {$status}. Repair the partial migration before continuing."
            );
        }

        $stored = (string) ($history['checksum'] ?? '');
        if ($stored === '') {
            // Existing VanAssist installations predate checksums. Trust and pin
            // the currently deployed file once during the runner upgrade.
            Database::query(
                'UPDATE migrations SET checksum = ?, completed_at = COALESCE(completed_at, ran_at) WHERE migration = ?',
                [$checksum, $name]
            );
            return;
        }

        if (!hash_equals($stored, $checksum)) {
            throw new RuntimeException(
                "Applied migration {$name} has changed (checksum mismatch). Restore the original file."
            );
        }
    }

    private function runFile(string $file): void
    {
        $sql = (string) file_get_contents($file);
        $pdo = Database::connection();

        foreach ($this->splitStatements($sql) as $statement) {
            $pdo->exec($statement);
        }
    }

    /** @return array<int,string> */
    private function splitStatements(string $sql): array
    {
        // Strip full-line SQL comments.
        $lines = preg_split('/\R/', $sql) ?: [];
        $clean = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*--/', $line)) {
                continue;
            }
            $clean[] = $line;
        }
        $sql = implode("\n", $clean);

        $parts = array_map('trim', explode(';', $sql));
        return array_values(array_filter($parts, static fn ($s) => $s !== ''));
    }

    public function pending(): array
    {
        $this->ensureMigrationsTable();
        $dirty = Database::selectOne(
            "SELECT migration, status FROM migrations WHERE status <> 'succeeded' ORDER BY id LIMIT 1"
        );
        if ($dirty !== null) {
            throw new RuntimeException(
                "Migration {$dirty['migration']} is marked {$dirty['status']}; pending state is unsafe until repaired."
            );
        }

        $applied = array_column(
            Database::select("SELECT migration FROM migrations WHERE status = 'succeeded'"),
            'migration'
        );
        $files = array_map('basename', glob($this->path . '/*.sql') ?: []);
        return array_values(array_diff($files, $applied));
    }

    private function acquireLock(): void
    {
        $acquired = (int) Database::scalar(
            'SELECT GET_LOCK(?, ?)',
            [self::LOCK_NAME, self::LOCK_TIMEOUT_SECONDS]
        );
        if ($acquired !== 1) {
            throw new RuntimeException('Could not acquire the database migration lock');
        }
    }

    private function releaseLock(): void
    {
        try {
            Database::scalar('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
        } catch (Throwable) {
            // The connection may have closed after a migration failure. MySQL
            // releases advisory locks automatically when the session ends.
        }
    }

    private function ensureColumn(string $name, string $definition): void
    {
        $column = Database::selectOne(
            'SELECT COLUMN_NAME FROM information_schema.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            ['migrations', $name]
        );
        if ($column === null) {
            Database::connection()->exec("ALTER TABLE migrations ADD COLUMN {$name} {$definition}");
        }
    }
}
