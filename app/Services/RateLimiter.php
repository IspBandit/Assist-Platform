<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use DateTimeImmutable;
use RuntimeException;
use Throwable;

final class RateLimiter
{
    /** @param array<int,string> $subjects */
    public static function blocked(string $action, array $subjects): bool
    {
        self::validateAction($action);
        foreach ($subjects as $subject) {
            $row = Database::selectOne(
                'SELECT id FROM rate_limit_buckets '
                . 'WHERE action_key = ? AND subject_hash = ? AND blocked_until > NOW() LIMIT 1',
                [$action, self::hashSubject($action, $subject)]
            );
            if ($row !== null) {
                return true;
            }
        }
        return false;
    }

    /** @param array<int,string> $subjects */
    public static function hit(
        string $action,
        array $subjects,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds,
    ): void {
        self::validateAction($action);
        if ($maxAttempts < 1 || $windowSeconds < 1 || $blockSeconds < 1) {
            throw new RuntimeException('Rate-limit thresholds must be positive');
        }

        foreach (array_values(array_unique($subjects)) as $subject) {
            self::hitSubject($action, $subject, $maxAttempts, $windowSeconds, $blockSeconds);
        }
    }

    /** @param array<int,string> $subjects */
    public static function clear(string $action, array $subjects): void
    {
        self::validateAction($action);
        foreach (array_values(array_unique($subjects)) as $subject) {
            Database::query(
                'DELETE FROM rate_limit_buckets WHERE action_key = ? AND subject_hash = ?',
                [$action, self::hashSubject($action, $subject)]
            );
        }
    }

    public static function prune(int $retentionHours = 48): int
    {
        if ($retentionHours < 1) {
            throw new RuntimeException('Rate-limit retention must be positive');
        }

        return Database::affecting(
            'DELETE FROM rate_limit_buckets '
            . 'WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL ? HOUR) '
            . 'AND (blocked_until IS NULL OR blocked_until <= NOW())',
            [$retentionHours]
        );
    }

    private static function hitSubject(
        string $action,
        string $subject,
        int $maxAttempts,
        int $windowSeconds,
        int $blockSeconds,
    ): void {
        $now = new DateTimeImmutable((string) Database::scalar('SELECT NOW()'));
        $hash = self::hashSubject($action, $subject);

        Database::beginTransaction();
        try {
            Database::query(
                'INSERT INTO rate_limit_buckets '
                . '(action_key, subject_hash, attempts, window_started_at, blocked_until, '
                . 'last_attempt_at, created_at, updated_at) VALUES (?, ?, 0, ?, NULL, ?, ?, ?) '
                . 'ON DUPLICATE KEY UPDATE id = id',
                [
                    $action,
                    $hash,
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                ]
            );
            $row = Database::selectOne(
                'SELECT id, attempts, window_started_at, blocked_until '
                . 'FROM rate_limit_buckets WHERE action_key = ? AND subject_hash = ? FOR UPDATE',
                [$action, $hash]
            );
            if ($row === null) {
                throw new RuntimeException('Unable to create rate-limit bucket');
            }

            $windowStart = new DateTimeImmutable((string) $row['window_started_at']);
            $windowExpired = $windowStart->modify("+{$windowSeconds} seconds") <= $now;
            $attempts = $windowExpired ? 1 : ((int) $row['attempts'] + 1);
            $blockedUntil = $attempts >= $maxAttempts
                ? $now->modify("+{$blockSeconds} seconds")->format('Y-m-d H:i:s')
                : null;

            Database::query(
                'UPDATE rate_limit_buckets SET attempts = ?, window_started_at = ?, '
                . 'blocked_until = ?, last_attempt_at = ?, updated_at = ? WHERE id = ?',
                [
                    $attempts,
                    $windowExpired ? $now->format('Y-m-d H:i:s') : $windowStart->format('Y-m-d H:i:s'),
                    $blockedUntil,
                    $now->format('Y-m-d H:i:s'),
                    $now->format('Y-m-d H:i:s'),
                    (int) $row['id'],
                ]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    private static function hashSubject(string $action, string $subject): string
    {
        $key = (string) Config::get('app.key', '');
        if ($key === '') {
            throw new RuntimeException('APP_KEY is required for privacy-safe rate limiting');
        }

        return hash_hmac('sha256', $action . '|' . strtolower(trim($subject)), $key);
    }

    private static function validateAction(string $action): void
    {
        if (!preg_match('/^[a-z][a-z0-9_.-]{1,79}$/', $action)) {
            throw new RuntimeException('Invalid rate-limit action key');
        }
    }
}
