<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Database;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use RuntimeException;
use Throwable;

/**
 * Staged marketing broadcasts. Every campaign must pass an internal test,
 * a 25-recipient pilot and an explicit review before larger daily batches.
 */
final class NotificationService
{
    public const STAGE_LIMITS = [
        'pilot' => 25,
        'daily_50' => 50,
        'daily_100' => 100,
    ];

    /** @return array{recipients:int,remaining:int,limited:bool} */
    public static function dispatch(int $notificationId): array
    {
        return self::queueStage($notificationId, 'pilot', null);
    }

    public static function queueTest(int $notificationId, string $recipientEmail, ?int $userId): bool
    {
        $email = strtolower(trim($recipientEmail));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Enter a valid internal test email address.');
        }

        return self::withNotificationBrand($notificationId, static function (array $notification) use ($notificationId, $email, $userId): bool {
            if (in_array((string) $notification['status'], ['sent', 'cancelled'], true)) {
                throw new RuntimeException('This campaign can no longer be tested.');
            }
            $subject = '[TEST — NOT SENT TO PROVIDERS] ' . (string) $notification['title'];
            $unsubscribeUrl = EmailSuppression::unsubscribeUrl($email);
            $queueId = EmailQueue::queueRawId(
                $email,
                null,
                $subject,
                self::wrap($subject, (string) ($notification['body'] ?? ''), $email, true),
                trim(strip_tags((string) ($notification['body'] ?? ''))) . "\n\nInternal campaign test.\nUnsubscribe: {$unsubscribeUrl}",
                null,
                null,
                'transactional',
                $notificationId
            );
            if ($queueId === null) {
                return false;
            }
            Database::query(
                'INSERT INTO notification_test_deliveries (notification_id,queue_id,recipient_email,queued_by,created_at) VALUES (?,?,?,?,NOW())',
                [$notificationId, $queueId, $email, $userId]
            );
            Database::query("UPDATE notifications SET delivery_stage='test', updated_at=NOW() WHERE id=?", [$notificationId]);
            return true;
        });
    }

    /** @return array{recipients:int,remaining:int,limited:bool} */
    public static function queueStage(int $notificationId, string $targetStage, ?int $reviewedBy): array
    {
        if (!isset(self::STAGE_LIMITS[$targetStage])) {
            throw new RuntimeException('Unknown campaign stage.');
        }

        return self::withNotificationBrand($notificationId, static function (array $notification) use ($notificationId, $targetStage, $reviewedBy): array {
            self::assertTransition((string) $notification['delivery_stage'], $targetStage);
            if (in_array((string) $notification['status'], ['sent', 'cancelled'], true)) {
                throw new RuntimeException('This campaign can no longer be queued.');
            }

            $recipients = BroadcastAudience::resolve(
                (string) $notification['audience_type'],
                $notification['town_id'] !== null ? (int) $notification['town_id'] : null,
                $notification['region_id'] !== null ? (int) $notification['region_id'] : null,
                $notification['category_id'] !== null ? (int) $notification['category_id'] : null,
            );
            $recipients = CampaignRecipientManager::filter($notificationId, $recipients);
            $existing = Database::select('SELECT email FROM notification_recipients WHERE notification_id=?', [$notificationId]);
            $seen = array_fill_keys(array_map(static fn (array $row): string => strtolower((string) $row['email']), $existing), true);
            $eligible = array_values(array_filter($recipients, static fn (array $row): bool => !isset($seen[strtolower($row['email'])])));

            $stageLimit = self::STAGE_LIMITS[$targetStage];
            $queuedLast24Hours = (int) Database::scalar(
                "SELECT COUNT(*) FROM notification_recipients WHERE notification_id=? AND status IN ('queued','sent') AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)",
                [$notificationId]
            );
            $available = $targetStage === 'pilot'
                ? max(0, $stageLimit - (int) Database::scalar("SELECT COUNT(*) FROM notification_recipients WHERE notification_id=? AND delivery_stage='pilot' AND status IN ('queued','sent')", [$notificationId]))
                : max(0, $stageLimit - $queuedLast24Hours);
            $batch = array_slice($eligible, 0, $available);

            $count = 0;
            foreach ($batch as $recipient) {
                $email = (string) $recipient['email'];
                $unsubscribeUrl = EmailSuppression::unsubscribeUrl($email);
                Database::beginTransaction();
                try {
                    $recipientId = Database::insert(
                        "INSERT INTO notification_recipients (notification_id,queue_id,user_id,email,status,delivery_stage,created_at) VALUES (?,NULL,?,?,'suppressed',?,NOW())",
                        [$notificationId, $recipient['user_id'], $email, $targetStage]
                    );
                    $queueId = EmailQueue::queueRawId(
                        $email,
                        $recipient['name'] ?: null,
                        (string) $notification['title'],
                        self::wrap((string) $notification['title'], (string) ($notification['body'] ?? ''), $email),
                        trim(strip_tags((string) ($notification['body'] ?? ''))) . "\n\nUnsubscribe from marketing email: {$unsubscribeUrl}",
                        null,
                        null,
                        'marketing',
                        $notificationId
                    );
                    if ($queueId !== null) {
                        Database::query("UPDATE notification_recipients SET queue_id=?,status='queued' WHERE id=?", [$queueId, $recipientId]);
                        $count++;
                    }
                    Database::commit();
                } catch (Throwable $error) {
                    Database::rollBack();
                    throw $error;
                }
            }

            $remaining = max(0, count($eligible) - count($batch));
            $complete = $remaining === 0;
            Database::query(
                'UPDATE notifications SET status=?, delivery_stage=?, recipient_count=recipient_count+?, last_batch_at=NOW(), '
                . 'stage_reviewed_at=?, stage_reviewed_by=?, sent_at=?, updated_at=NOW() WHERE id=?',
                [
                    $complete ? 'sent' : 'sending',
                    $complete ? 'complete' : $targetStage,
                    $count,
                    $reviewedBy !== null ? date('Y-m-d H:i:s') : $notification['stage_reviewed_at'],
                    $reviewedBy ?? $notification['stage_reviewed_by'],
                    $complete ? date('Y-m-d H:i:s') : null,
                    $notificationId,
                ]
            );

            return ['recipients' => $count, 'remaining' => $remaining, 'limited' => $available === 0 && $remaining > 0];
        });
    }

    private static function assertTransition(string $current, string $target): void
    {
        $allowed = [
            'test' => ['pilot'],
            'pilot' => ['daily_50'],
            'daily_50' => ['daily_50', 'daily_100'],
            'daily_100' => ['daily_100'],
        ];
        if (!in_array($target, $allowed[$current] ?? [], true)) {
            throw new RuntimeException('Complete and review the earlier campaign stage first.');
        }
    }

    /**
     * @template T
     * @param callable(array<string,mixed>):T $callback
     * @return T
     */
    private static function withNotificationBrand(int $notificationId, callable $callback): mixed
    {
        $notification = Database::selectOne('SELECT * FROM notifications WHERE id=?', [$notificationId]);
        if ($notification === null) {
            throw new RuntimeException('Campaign not found.');
        }
        $registry = BrandRegistry::fromArray((array) Config::get('brands.registry', []));
        $brand = $registry->forDatabaseId((int) $notification['brand_id']);
        if ($brand === null) {
            throw new RuntimeException('Campaign references an unknown brand.');
        }
        $previous = BrandContext::hasCurrent() ? BrandContext::current() : null;
        BrandContext::set($brand);
        try {
            return $callback($notification);
        } finally {
            self::restoreBrand($previous);
        }
    }

    private static function restoreBrand(?Brand $brand): void
    {
        if ($brand !== null) {
            BrandContext::set($brand);
            return;
        }
        BrandContext::clear();
    }

    public static function wrap(string $title, string $body, string $recipientEmail, bool $isTest = false): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $brand = current_brand();
        $brandName = htmlspecialchars($brand->name(), ENT_QUOTES, 'UTF-8');
        $legalName = htmlspecialchars($brand->legalName(), ENT_QUOTES, 'UTF-8');
        $supportEmail = htmlspecialchars((string) ($brand->contact()['support_email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $website = htmlspecialchars($brand->url(), ENT_QUOTES, 'UTF-8');
        $unsubscribeUrl = htmlspecialchars(EmailSuppression::unsubscribeUrl($recipientEmail), ENT_QUOTES, 'UTF-8');
        $reason = $isTest
            ? 'This is an internal campaign test and has not been sent to providers.'
            : 'Consent for relevant ' . $brandName . ' email updates is recorded for this email address.';
        return '<div style="font-family:Arial,Helvetica,sans-serif;max-width:600px;margin:0 auto;color:#2b2f33">'
            . '<h2 style="color:#0f6e6e">' . $safeTitle . '</h2><div>' . $body . '</div>'
            . '<hr style="border:none;border-top:1px solid #e3e0d8;margin:24px 0">'
            . '<p style="font-size:12px;color:#646a70">' . $reason . ' '
            . '<a href="' . $unsubscribeUrl . '">Unsubscribe from marketing email</a>.</p>'
            . '<p style="font-size:12px;color:#646a70">Sent by ' . $legalName . '. '
            . ($supportEmail !== '' ? 'Contact: <a href="mailto:' . $supportEmail . '">' . $supportEmail . '</a> · ' : '')
            . '<a href="' . $website . '">' . $brandName . ' website</a></p></div>';
    }
}
