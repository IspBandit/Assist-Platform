<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;
use RuntimeException;

/**
 * Thin PDO wrapper providing a shared connection and prepared-statement
 * helpers. All queries throughout the app go through here so we never
 * concatenate raw user input into SQL.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        return self::connect();
    }

    /** Drop the cached PDO handle and open a fresh connection. */
    public static function reconnect(): PDO
    {
        self::$pdo = null;

        return self::connect();
    }

    private static function connect(): PDO
    {
        $cfg = Config::get('database');
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            (int) $cfg['port'],
            $cfg['name'],
            $cfg['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $cfg['user'], $cfg['password'], $cfg['options']);
            self::relaxGroupByMode(self::$pdo);
        } catch (PDOException $e) {
            // Never leak credentials. Log full detail elsewhere.
            throw new RuntimeException('Database connection failed.', 0, $e);
        }

        return self::$pdo;
    }

    /**
     * Connect using explicit parameters (used by the installer to test
     * credentials before they are saved to .env).
     */
    public static function testConnection(array $cfg): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;%scharset=utf8mb4',
            $cfg['host'],
            (int) ($cfg['port'] ?? 3306),
            !empty($cfg['name']) ? 'dbname=' . $cfg['name'] . ';' : ''
        );

        return new PDO($dsn, $cfg['user'], $cfg['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Remove ONLY_FULL_GROUP_BY from the session sql_mode. Several reporting
     * queries group by a joined table's key (e.g. GROUP BY sr.town_id while
     * selecting towns.*), which relies on functional dependency. MySQL 5.7+
     * detects this, but stricter/older hosts (and some MariaDB builds) with
     * ONLY_FULL_GROUP_BY enabled reject it with error 1055. Dropping just that
     * one flag keeps every other strict-mode protection in place.
     */
    private static function relaxGroupByMode(PDO $pdo): void
    {
        try {
            $pdo->exec(
                "SET SESSION sql_mode = REPLACE(REPLACE(REPLACE(@@SESSION.sql_mode,"
                . "'ONLY_FULL_GROUP_BY,',''),',ONLY_FULL_GROUP_BY',''),'ONLY_FULL_GROUP_BY','')"
            );
        } catch (PDOException) {
            // Non-fatal: if we can't adjust sql_mode, leave the connection as-is.
        }
    }

    public static function setConnection(PDO $pdo): void
    {
        self::$pdo = $pdo;
    }

    public static function query(string $sql, array $params = []): PDOStatement
    {
        try {
            $stmt = self::connection()->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        } catch (PDOException $e) {
            if (!self::isGoneAway($e)) {
                throw $e;
            }
            // Shared hosts often drop idle/long-lived connections mid-import.
            self::reconnect();
            $stmt = self::connection()->prepare($sql);
            $stmt->execute($params);

            return $stmt;
        }
    }

    private static function isGoneAway(PDOException $e): bool
    {
        $code = (string) $e->getCode();
        $msg = $e->getMessage();

        return $code === 'HY000'
            && (str_contains($msg, '2006')
                || str_contains($msg, '2013')
                || stripos($msg, 'server has gone away') !== false
                || stripos($msg, 'Lost connection') !== false);
    }

    /** @return array<int,array<string,mixed>> */
    public static function select(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        return self::query($sql, $params)->fetchColumn();
    }

    public static function insert(string $sql, array $params = []): int
    {
        self::query($sql, $params);
        return (int) self::connection()->lastInsertId();
    }

    public static function affecting(string $sql, array $params = []): int
    {
        return self::query($sql, $params)->rowCount();
    }

    public static function beginTransaction(): void
    {
        self::connection()->beginTransaction();
    }

    public static function commit(): void
    {
        self::connection()->commit();
    }

    public static function rollBack(): void
    {
        if (self::connection()->inTransaction()) {
            self::connection()->rollBack();
        }
    }

    /** Used by the installer to confirm the schema has been created. */
    public static function tableExists(string $table): bool
    {
        $sql = 'SELECT COUNT(*) FROM information_schema.tables '
            . 'WHERE table_schema = DATABASE() AND table_name = ?';
        return (int) self::scalar($sql, [$table]) > 0;
    }
}
