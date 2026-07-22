<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Core\Logger;
use App\Platform\Brand\BrandRegistry;
use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

/**
 * Processes the email queue using PHPMailer over SMTP. Designed to be called
 * from cron. Never uses PHP mail(). If PHPMailer is not installed (composer
 * dependencies not yet pulled), the queue is left pending and a notice logged.
 */
final class Mailer
{
    /** @return array{processed:int,sent:int,failed:int} */
    public static function processQueue(int $batch = 25): array
    {
        $result = ['processed' => 0, 'sent' => 0, 'failed' => 0];

        // Email can be delivered either via PHPMailer (when Composer deps are
        // installed) or the built-in dependency-free SmtpClient fallback. Only a
        // missing SMTP host means we genuinely cannot send — leave the queue
        // pending in that case rather than burning delivery attempts.
        $cfg = self::config();
        if (trim((string) $cfg['host']) === '') {
            Logger::warning('SMTP host not configured; email queue left pending. Set mail settings in Admin → Settings.', [], 'email');
            return $result;
        }

        $maxAttempts = (int) config('mail.max_attempts', 3);
        $rows = self::claimBatch($batch, $maxAttempts);

        $transport = class_exists(PHPMailer::class) ? 'PHPMailer' : 'SmtpClient (built-in)';
        Logger::info('Processing email queue: ' . count($rows) . ' pending item(s).', [
            'transport'  => $transport,
            'host'       => $cfg['host'],
            'port'       => $cfg['port'],
            'encryption' => $cfg['encryption'] !== '' ? $cfg['encryption'] : 'plain',
            'username'   => $cfg['username'],
            'from'       => $cfg['from_address'],
        ], 'email');

        foreach ($rows as $row) {
            $result['processed']++;

            Logger::info('Queue #' . $row['id'] . ' -> sending to ' . $row['recipient_email'] . ' [' . $row['subject'] . '] via ' . $transport . '.', [], 'email');

            try {
                self::send($row);
            } catch (Throwable $e) {
                $attempts = (int) $row['attempts'];
                $status = $attempts >= $maxAttempts ? 'failed' : 'pending';
                Logger::error('Queue #' . $row['id'] . ' -> ' . strtoupper($status) . ' (attempt ' . $attempts . '/' . $maxAttempts . '): ' . $e->getMessage(), [
                    'to' => $row['recipient_email'],
                ], 'email');
                self::markFailedAttempt($row, $status, $e);
                $result['failed']++;
                continue;
            }

            // Delivery succeeded. If durable completion fails, stop the worker:
            // blindly retrying could send a duplicate message.
            self::markSent($row);
            Logger::info('Queue #' . $row['id'] . ' -> SENT to ' . $row['recipient_email'] . '.', [], 'email');
            $result['sent']++;
        }

        return $result;
    }

    /** @return array<int,array<string,mixed>> */
    private static function claimBatch(int $batch, int $maxAttempts): array
    {
        $batch = max(1, min($batch, 100));
        $maxAttempts = max(1, $maxAttempts);
        $leaseToken = bin2hex(random_bytes(16));

        Database::query(
            "UPDATE email_queue SET status = 'failed', lease_token = NULL, leased_until = NULL, "
            . "last_error = 'Worker lease expired after maximum attempts' "
            . "WHERE status = 'processing' AND leased_until <= NOW() AND attempts >= ?",
            [$maxAttempts]
        );

        Database::beginTransaction();
        try {
            $ids = Database::select(
                "SELECT id FROM email_queue WHERE attempts < ? "
                . "AND (next_attempt_at IS NULL OR next_attempt_at <= NOW()) "
                . "AND (scheduled_at IS NULL OR scheduled_at <= NOW()) "
                . "AND (status = 'pending' OR (status = 'processing' AND leased_until <= NOW())) "
                . 'ORDER BY id ASC LIMIT ? FOR UPDATE SKIP LOCKED',
                [$maxAttempts, $batch]
            );
            if ($ids === []) {
                Database::commit();
                return [];
            }

            $idValues = array_map(static fn (array $row): int => (int) $row['id'], $ids);
            $placeholders = implode(',', array_fill(0, count($idValues), '?'));
            Database::query(
                "UPDATE email_queue SET status = 'processing', lease_token = ?, "
                . 'leased_until = DATE_ADD(NOW(), INTERVAL 15 MINUTE), '
                . 'attempts = attempts + 1, last_attempt_at = NOW() '
                . "WHERE id IN ({$placeholders})",
                array_merge([$leaseToken], $idValues)
            );
            $rows = Database::select(
                "SELECT * FROM email_queue WHERE lease_token = ? AND id IN ({$placeholders}) ORDER BY id ASC",
                array_merge([$leaseToken], $idValues)
            );
            Database::commit();
            return $rows;
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $row */
    private static function markSent(array $row): void
    {
        Database::beginTransaction();
        try {
            $updated = Database::affecting(
                "UPDATE email_queue SET status = 'sent', sent_at = NOW(), lease_token = NULL, "
                . 'leased_until = NULL, next_attempt_at = NULL, last_error = NULL '
                . "WHERE id = ? AND status = 'processing' AND lease_token = ?",
                [$row['id'], $row['lease_token']]
            );
            if ($updated !== 1) {
                throw new \RuntimeException('Email queue lease was lost before completion');
            }
            Database::query(
                "INSERT INTO email_log (queue_id, recipient_email, subject, status, created_at) "
                . "VALUES (?, ?, ?, 'sent', NOW())",
                [$row['id'], $row['recipient_email'], $row['subject']]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /** @param array<string,mixed> $row */
    private static function markFailedAttempt(array $row, string $status, Throwable $error): void
    {
        $message = substr($error->getMessage(), 0, 500);
        $backoffSeconds = min(3600, 60 * (2 ** max(0, (int) $row['attempts'] - 1)));

        Database::beginTransaction();
        try {
            $updated = Database::affecting(
                'UPDATE email_queue SET status = ?, lease_token = NULL, leased_until = NULL, '
                . "next_attempt_at = CASE WHEN ? = 'pending' "
                . 'THEN DATE_ADD(NOW(), INTERVAL ? SECOND) ELSE NULL END, '
                . 'last_error = ? WHERE id = ? AND lease_token = ?',
                [
                    $status,
                    $status,
                    $backoffSeconds,
                    $message,
                    $row['id'],
                    $row['lease_token'],
                ]
            );
            if ($updated !== 1) {
                throw new \RuntimeException('Email queue lease was lost while recording failure');
            }
            Database::query(
                "INSERT INTO email_log (queue_id, recipient_email, subject, status, error, created_at) "
                . "VALUES (?, ?, ?, 'failed', ?, NOW())",
                [$row['id'], $row['recipient_email'], $row['subject'], $message]
            );
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    /**
     * Effective mail configuration: values saved in the admin dashboard
     * (site_settings) take precedence, falling back to the .env config when a
     * field has not been set in the database.
     *
     * @return array<string,mixed>
     */
    public static function config(?int $brandDatabaseId = null): array
    {
        $env = config('mail');
        $db = static fn (string $key, string $envKey): string =>
            trim((string) Settings::get($key, '')) !== ''
                ? trim((string) Settings::get($key, ''))
                : (string) ($env[$envKey] ?? '');

        $encryption = trim((string) Settings::get('mail_encryption', ''));
        if ($encryption === '') {
            $encryption = (string) ($env['encryption'] ?? '');
        }
        if (strtolower($encryption) === 'none') {
            $encryption = '';
        }

        $username = $db('mail_username', 'username');
        $fromAddress = $db('mail_from_address', 'from_address');
        // Many hosts reject an empty/ mismatched envelope sender — default the
        // From address to the authenticated SMTP username when not set.
        if (trim($fromAddress) === '') {
            $fromAddress = $username;
        }

        $fromName = $db('mail_from_name', 'from_name') ?: 'Assist Platform';

        if ($brandDatabaseId !== null) {
            $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
            $brand = $registry->forDatabaseId($brandDatabaseId);
            if ($brand === null) {
                throw new RuntimeException("Email queue references unknown brand database ID {$brandDatabaseId}");
            }

            $contact = $brand->contact();
            $fromAddress = trim((string) ($contact['sender_email'] ?? ''));
            $fromName = trim((string) ($contact['sender_name'] ?? '')) ?: $brand->name();
            if ($fromAddress === '') {
                throw new RuntimeException("Outbound sender is not configured for {$brand->name()}");
            }
        }

        return [
            'host'         => $db('mail_host', 'host'),
            'port'         => (int) ($db('mail_port', 'port') ?: 587),
            'username'     => $username,
            'password'     => SecretCipher::decrypt($db('mail_password', 'password')),
            'encryption'   => $encryption,
            'from_address' => $fromAddress,
            'from_name'    => $fromName,
            'max_attempts' => (int) ($env['max_attempts'] ?? 3),
        ];
    }

    private static function send(array $row): void
    {
        $cfg = self::config((int) $row['brand_id']);

        // Prefer PHPMailer when installed; otherwise use the built-in SMTP client
        // so delivery works on hosts without Composer dependencies.
        if (!class_exists(PHPMailer::class)) {
            SmtpClient::send(
                $cfg,
                (string) $row['recipient_email'],
                (string) ($row['recipient_name'] ?? ''),
                (string) $row['subject'],
                (string) $row['html_body'],
                (string) ($row['text_body'] ?? '')
            );
            return;
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = (string) $cfg['host'];
        $mail->Port = (int) $cfg['port'];
        $mail->SMTPAuth = $cfg['username'] !== '';
        if ($mail->SMTPAuth) {
            $mail->Username = (string) $cfg['username'];
            $mail->Password = (string) $cfg['password'];
        }
        if (!empty($cfg['encryption'])) {
            $mail->SMTPSecure = (string) $cfg['encryption'];
        }
        $mail->CharSet = 'UTF-8';
        $mail->setFrom((string) $cfg['from_address'], (string) $cfg['from_name']);
        $mail->addAddress((string) $row['recipient_email'], (string) ($row['recipient_name'] ?? ''));
        $mail->Subject = (string) $row['subject'];
        $mail->isHTML(true);
        $mail->Body = (string) $row['html_body'];
        $mail->AltBody = (string) ($row['text_body'] ?: strip_tags((string) $row['html_body']));
        $mail->send();
    }
}
