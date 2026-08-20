<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use RuntimeException;

final class EmailSuppression
{
    public static function isSuppressed(string $email, string $messageType): bool
    {
        $email = self::normalise($email);
        if ($email === '') {
            return true;
        }

        $scope = match ($messageType) {
            'marketing' => ['all', 'marketing'],
            'directory_accuracy' => ['all', 'directory_accuracy'],
            default => ['all'],
        };
        $placeholders = implode(',', array_fill(0, count($scope), '?'));
        return (int) Database::scalar(
            "SELECT COUNT(*) FROM email_suppressions WHERE email=? AND scope IN ({$placeholders})",
            array_merge([$email], $scope)
        ) > 0;
    }

    public static function suppressMarketing(string $email, string $source = 'public_unsubscribe'): void
    {
        $email = self::normalise($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Database::query(
            "INSERT INTO email_suppressions (email,reason,scope,source,created_at) VALUES (?,'marketing_opt_out','marketing',?,NOW()) "
            . "ON DUPLICATE KEY UPDATE scope='marketing',source=VALUES(source),updated_at=NOW()",
            [$email, mb_substr($source, 0, 80)]
        );
        Database::query('UPDATE users SET marketing_opt_in=0,updated_at=NOW() WHERE LOWER(email)=?', [$email]);
        self::cancelPending($email, 'marketing');
    }

    public static function suppressAll(string $email, string $reason, string $source): void
    {
        $email = self::normalise($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }
        $reason = in_array($reason, ['hard_bounce', 'complaint', 'admin'], true) ? $reason : 'admin';
        Database::query(
            "INSERT INTO email_suppressions (email,reason,scope,source,created_at) VALUES (?,?,'all',?,NOW()) "
            . "ON DUPLICATE KEY UPDATE scope='all',source=VALUES(source),updated_at=NOW()",
            [$email, $reason, mb_substr(trim($source), 0, 80)]
        );
        self::cancelPending($email, 'all');
    }

    public static function suppressDirectoryAccuracy(string $email, string $source = 'public_directory_notice_opt_out'): void
    {
        $email = self::normalise($email);
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        Database::query(
            "INSERT INTO email_suppressions (email,reason,scope,source,created_at) VALUES (?,'directory_notice_opt_out','directory_accuracy',?,NOW()) "
            . "ON DUPLICATE KEY UPDATE scope='directory_accuracy',source=VALUES(source),updated_at=NOW()",
            [$email, mb_substr($source, 0, 80)]
        );
        self::cancelPending($email, 'directory_accuracy');
    }

    public static function unsubscribeUrl(string $email): string
    {
        $email = self::normalise($email);
        $signature = hash_hmac('sha256', $email, self::key());
        return url('email/unsubscribe?email=' . rawurlencode($email) . '&signature=' . $signature);
    }

    public static function verify(string $email, string $signature): bool
    {
        $email = self::normalise($email);
        return $email !== '' && hash_equals(hash_hmac('sha256', $email, self::key()), strtolower(trim($signature)));
    }

    public static function directoryNoticeOptOutUrl(string $email): string
    {
        $email = self::normalise($email);
        $signature = hash_hmac('sha256', 'directory_accuracy|' . $email, self::key());
        return url('email/listing-notices/stop?email=' . rawurlencode($email) . '&signature=' . $signature);
    }

    public static function verifyDirectoryNotice(string $email, string $signature): bool
    {
        $email = self::normalise($email);
        return $email !== '' && hash_equals(
            hash_hmac('sha256', 'directory_accuracy|' . $email, self::key()),
            strtolower(trim($signature))
        );
    }

    private static function normalise(string $email): string
    {
        return strtolower(trim($email));
    }

    private static function cancelPending(string $email, string $scope): void
    {
        $typeWhere = match ($scope) {
            'marketing' => " AND message_type='marketing'",
            'directory_accuracy' => " AND message_type='directory_accuracy'",
            default => '',
        };
        Database::query(
            "UPDATE email_queue SET status='cancelled',last_error='Recipient suppressed before delivery' "
            . "WHERE LOWER(recipient_email)=? AND status='pending'{$typeWhere}",
            [$email]
        );
        Database::query(
            "UPDATE notification_recipients nr INNER JOIN email_queue eq ON eq.id=nr.queue_id SET nr.status='suppressed' "
            . "WHERE LOWER(nr.email)=? AND eq.status='cancelled'{$typeWhere}",
            [$email]
        );
    }

    private static function key(): string
    {
        $key = (string) Config::get('app.key', '');
        if (strlen($key) < 16) {
            throw new RuntimeException('APP_KEY is required for signed email preference links');
        }
        return $key;
    }
}
