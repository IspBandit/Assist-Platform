<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Database;
use App\Services\EmailQueue;
use App\Services\FeatureFlag;
use App\Services\Settings;
use Throwable;

/**
 * Cron-driven customer outcome follow-ups (cPanel-friendly, batch, email-only
 * unless a real SMS provider is configured). Two phases per run:
 *
 *   1. autoEnqueue() — queue a single feedback follow-up for settled requests
 *      that have no follow-up yet and no customer-confirmed outcome.
 *   2. processDue()  — send due follow-ups via the existing EmailQueue with a
 *      tokenised, login-free response link, respecting consent/opt-out.
 *
 * A response token is generated at send time (only its hash is stored), so a
 * queued-but-unsent follow-up never carries a live link.
 */
final class FollowupService
{
    private const SEND_BATCH = 50;
    private const ENQUEUE_BATCH = 200;
    private const MAX_AGE_DAYS = 120;

    /** @return array<string,mixed> */
    public static function run(): array
    {
        if (!FeatureFlag::enabled(ActivityTracker::FLAG, false)) {
            return ['note' => 'demand_analytics disabled; skipped'];
        }
        try {
            $enqueued = self::autoEnqueue();
            $sent = self::processDue();
            return ['enqueued' => $enqueued, 'sent' => $sent];
        } catch (Throwable $e) {
            return ['error' => substr($e->getMessage(), 0, 180)];
        }
    }

    private static function autoEnqueue(): int
    {
        $delayDays = max(1, (int) Settings::get('followup_delay_days', '7'));

        $rows = Database::select(
            'SELECT sr.id, sr.customer_id FROM service_requests sr '
            . "WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND sr.contact_email <> '' "
            . 'AND sr.consent_privacy = 1 '
            . "AND sr.status NOT IN ('draft','awaiting_verification','cancelled','rejected','expired') "
            . 'AND sr.created_at <= DATE_SUB(NOW(), INTERVAL ? DAY) '
            . 'AND sr.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY) '
            . 'AND NOT EXISTS (SELECT 1 FROM customer_followups f WHERE f.request_id = sr.id) '
            . 'AND NOT EXISTS (SELECT 1 FROM service_outcomes o WHERE o.request_id = sr.id AND o.customer_confirmed = 1) '
            . 'LIMIT ' . self::ENQUEUE_BATCH,
            [$delayDays, self::MAX_AGE_DAYS]
        );

        $count = 0;
        foreach ($rows as $r) {
            try {
                Database::query(
                    'INSERT INTO customer_followups (request_id, customer_id, followup_type, channel, scheduled_for, '
                    . "status, attempts, created_at, updated_at) VALUES (?, ?, 'feedback', 'email', NOW(), 'pending', 0, NOW(), NOW())",
                    [(int) $r['id'], $r['customer_id'] !== null ? (int) $r['customer_id'] : null]
                );
                $count++;
            } catch (Throwable) {
            }
        }
        return $count;
    }

    private static function processDue(): int
    {
        $due = Database::select(
            'SELECT f.id, f.request_id, sr.reference, sr.contact_email, sr.contact_name, sr.status AS req_status '
            . 'FROM customer_followups f JOIN service_requests sr ON sr.id = f.request_id '
            . "WHERE f.status = 'pending' AND f.scheduled_for <= NOW() AND sr.deleted_at IS NULL "
            . "AND sr.contact_email <> '' AND sr.consent_privacy = 1 "
            . "AND sr.status NOT IN ('cancelled','rejected','expired') "
            . 'AND NOT EXISTS (SELECT 1 FROM service_outcomes o WHERE o.request_id = f.request_id AND o.customer_confirmed = 1) '
            . 'ORDER BY f.scheduled_for LIMIT ' . self::SEND_BATCH
        );

        $sent = 0;
        foreach ($due as $f) {
            try {
                $token = bin2hex(random_bytes(20));
                Database::query(
                    "UPDATE customer_followups SET status = 'sent', sent_at = NOW(), token_hash = ?, "
                    . 'attempts = attempts + 1, updated_at = NOW() WHERE id = ?',
                    [hash('sha256', $token), (int) $f['id']]
                );

                $ok = EmailQueue::queueTemplate('outcome_followup', (string) $f['contact_email'], (string) $f['contact_name'], [
                    'customer_name'     => (string) $f['contact_name'],
                    'request_reference' => (string) $f['reference'],
                    'action_url'        => url('followup/' . $token),
                ]);
                if ($ok) {
                    $sent++;
                }
            } catch (Throwable) {
            }
        }
        return $sent;
    }

    /** Resolve a follow-up by its raw token (login-free response links). */
    public static function findByToken(string $token): ?array
    {
        if ($token === '' || preg_match('/^[a-f0-9]{40}$/', $token) !== 1) {
            return null;
        }
        return Database::selectOne(
            'SELECT * FROM customer_followups WHERE token_hash = ? LIMIT 1',
            [hash('sha256', $token)]
        );
    }

    public static function markResponded(int $followupId, ?int $outcomeId = null): void
    {
        try {
            Database::query(
                "UPDATE customer_followups SET status = 'responded', responded_at = NOW(), "
                . 'outcome_id = COALESCE(?, outcome_id), updated_at = NOW() WHERE id = ?',
                [$outcomeId, $followupId]
            );
        } catch (Throwable) {
        }
    }
}
