<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use RuntimeException;

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

        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        $brand = $registry->forDatabaseId((int) $notification['brand_id']);
        if ($brand === null) {
            throw new RuntimeException('Broadcast references an unknown brand');
        }
        $previousBrand = BrandContext::hasCurrent() ? BrandContext::current() : null;
        BrandContext::set($brand);
        try {
            return self::dispatchLoaded($notificationId, $notification);
        } finally {
            self::restoreBrand($previousBrand);
        }
    }

    /** @param array<string,mixed> $notification @return array{recipients:int} */
    private static function dispatchLoaded(int $notificationId, array $notification): array
    {

        Database::query("UPDATE notifications SET status = 'sending', updated_at = NOW() WHERE id = ?", [$notificationId]);

        $recipients = BroadcastAudience::resolve(
            (string) $notification['audience_type'],
            $notification['town_id'] !== null ? (int) $notification['town_id'] : null,
            $notification['region_id'] !== null ? (int) $notification['region_id'] : null,
            $notification['category_id'] !== null ? (int) $notification['category_id'] : null,
        );

        $subject = (string) $notification['title'];
        $count = 0;
        foreach ($recipients as $r) {
            $unsubscribeUrl = EmailSuppression::unsubscribeUrl($r['email']);
            $bodyHtml = self::wrap($subject, (string) ($notification['body'] ?? ''), $r['email']);
            $bodyText = trim(strip_tags((string) ($notification['body'] ?? '')))
                . "\n\nUnsubscribe from marketing email: {$unsubscribeUrl}";
            $queued = EmailQueue::queueRaw($r['email'], $r['name'] ?: null, $subject, $bodyHtml, $bodyText, null, null, 'marketing');
            Database::query(
                'INSERT INTO notification_recipients (notification_id, user_id, email, status, created_at) VALUES (?, ?, ?, ?, NOW())',
                [$notificationId, $r['user_id'], $r['email'], $queued ? 'queued' : 'suppressed']
            );
            if ($queued) {
                $count++;
            }
        }

        Database::query(
            "UPDATE notifications SET status = 'sent', recipient_count = ?, sent_at = NOW(), updated_at = NOW() WHERE id = ?",
            [$count, $notificationId]
        );

        return ['recipients' => $count];
    }

    private static function restoreBrand(?Brand $brand): void
    {
        if ($brand !== null) {
            BrandContext::set($brand);
        } else {
            BrandContext::clear();
        }
    }

    /** Wrap a broadcast body in the active brand's email shell. */
    public static function wrap(string $title, string $body, string $recipientEmail): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $brand = current_brand();
        $brandName = htmlspecialchars($brand->name(), ENT_QUOTES, 'UTF-8');
        $legalName = htmlspecialchars($brand->legalName(), ENT_QUOTES, 'UTF-8');
        $supportEmail = htmlspecialchars((string) ($brand->contact()['support_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $website = htmlspecialchars($brand->url(), ENT_QUOTES, 'UTF-8');
        $unsubscribeUrl = htmlspecialchars(EmailSuppression::unsubscribeUrl($recipientEmail), ENT_QUOTES, 'UTF-8');
        // Body is trusted admin-authored HTML.
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#2b2f33">'
            . '<h2 style="color:#0f6e6e">' . $safeTitle . '</h2>'
            . '<div>' . $body . '</div>'
            . '<hr style="border:none;border-top:1px solid #e3e0d8;margin:24px 0">'
            . '<p style="font-size:12px;color:#8a8f94">You are receiving this ' . $brandName . ' update because you opted in or are an active provider. '
            . '<a href="' . $unsubscribeUrl . '">Unsubscribe from marketing email</a>.</p>'
            . '<p style="font-size:12px;color:#8a8f94">Sent by ' . $legalName . '. '
            . ($supportEmail !== '' ? 'Contact: <a href="mailto:' . $supportEmail . '">' . $supportEmail . '</a> · ' : '')
            . '<a href="' . $website . '">' . $brandName . ' website</a></p></div>';
    }
}
