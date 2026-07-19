<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Creates and dispatches admin broadcasts. A notification is resolved to its
 * audience at dispatch time (so the list is always fresh), recorded against
 * notification_recipients, and queued into email_queue for the Mailer cron.
 */
final class NotificationService
{
    /**
     * Resolve recipients, queue the emails and mark the notification sent.
     *
     * @return array{recipients:int}
     */
    public static function dispatch(int $notificationId): array
    {
        $notification = Database::selectOne('SELECT * FROM notifications WHERE id = ?', [$notificationId]);
        if ($notification === null || in_array($notification['status'], ['sent', 'cancelled'], true)) {
            return ['recipients' => 0];
        }

        Database::query("UPDATE notifications SET status = 'sending', updated_at = NOW() WHERE id = ?", [$notificationId]);

        $recipients = BroadcastAudience::resolve(
            (string) $notification['audience_type'],
            $notification['town_id'] !== null ? (int) $notification['town_id'] : null,
            $notification['region_id'] !== null ? (int) $notification['region_id'] : null,
            $notification['category_id'] !== null ? (int) $notification['category_id'] : null,
        );

        $subject = (string) $notification['title'];
        $bodyHtml = self::wrap($subject, (string) ($notification['body'] ?? ''));
        $bodyText = trim(strip_tags((string) ($notification['body'] ?? '')));

        $count = 0;
        foreach ($recipients as $r) {
            Database::query(
                'INSERT INTO notification_recipients (notification_id, user_id, email, status, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$notificationId, $r['user_id'], $r['email'], 'queued']
            );
            EmailQueue::queueRaw($r['email'], $r['name'] ?: null, $subject, $bodyHtml, $bodyText);
            $count++;
        }

        Database::query(
            "UPDATE notifications SET status = 'sent', recipient_count = ?, sent_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$count, $notificationId]
        );

        return ['recipients' => $count];
    }

    /** Wrap a broadcast body in the standard VanAssist email shell. */
    public static function wrap(string $title, string $body): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        // Body is trusted admin-authored HTML.
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#2b2f33">'
            . '<h2 style="color:#0f6e6e">' . $safeTitle . '</h2>'
            . '<div>' . $body . '</div>'
            . '<hr style="border:none;border-top:1px solid #e3e0d8;margin:24px 0">'
            . '<p style="font-size:12px;color:#8a8f94">You are receiving this VanAssist update because you opted in or are an active provider. '
            . 'Manage your preferences from your account settings.</p></div>';
    }
}
