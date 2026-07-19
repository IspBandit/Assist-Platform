<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Database;
use App\Services\FeatureFlag;
use Throwable;

/**
 * Owns the service_outcomes "record of truth" for which provider was actually
 * used for a request, plus the outcome_confirmations audit trail, the
 * confidence ladder, repeat-provider detection, reviews and saved providers.
 *
 * Confidence ladder (low -> high):
 *   inferred < contact_only < customer_reported < provider_reported
 *           < both_confirmed < admin_verified
 *
 * The primary "providers used" metric is built from confirmed/strongly
 * evidenced rows here, never from raw clicks.
 */
final class OutcomeService
{
    public static function enabled(): bool
    {
        try {
            return FeatureFlag::enabled(ActivityTracker::FLAG, false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Create or update the (request, provider) outcome row, append a
     * confirmation audit entry, recompute confidence + repeat flag, and emit
     * the matching funnel event. Returns the outcome id, or null on failure.
     *
     * @param array<string,mixed> $data status + optional booked/completed/
     *                                   satisfaction/value_band/etc.
     * @param string $role customer|provider|admin|system
     */
    public static function upsert(int $requestId, int $providerId, array $data, string $role): ?int
    {
        if ($providerId === 0) {
            return null;
        }
        try {
            if ($role === 'customer' && $requestId > 0 && !self::providerMatchedToRequest($requestId, $providerId)) {
                return null;
            }

            $req = $requestId > 0
                ? Database::selectOne('SELECT id, customer_id, primary_category_id, town_id, region_id FROM service_requests WHERE id = ?', [$requestId])
                : null;
            $customerId = $req['customer_id'] ?? ($data['customer_id'] ?? null);

            $existing = Database::selectOne(
                'SELECT * FROM service_outcomes WHERE request_id <=> ? AND provider_id = ?',
                [$requestId > 0 ? $requestId : null, $providerId]
            );

            $status = self::normaliseStatus((string) ($data['status'] ?? ($existing['status'] ?? 'contacted')));
            $now = date('Y-m-d H:i:s');

            $customerConfirmed = (int) (($existing['customer_confirmed'] ?? 0) || ($role === 'customer'));
            $providerConfirmed = (int) (($existing['provider_confirmed'] ?? 0) || ($role === 'provider'));
            $adminConfirmed = (int) (($existing['admin_confirmed'] ?? 0) || ($role === 'admin'));

            if ($existing === null) {
                $outcomeId = Database::insert(
                    'INSERT INTO service_outcomes '
                    . '(request_id, match_id, provider_id, customer_id, category_id, town_id, region_id, status, '
                    . 'confidence, used_via_vanassist, customer_confirmed, provider_confirmed, admin_confirmed, '
                    . 'issue_resolved, would_use_again, satisfaction_rating, value_band, work_type, cancellation_reason, '
                    . 'notes, contacted_at, created_at, updated_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                    [
                        $requestId > 0 ? $requestId : null,
                        self::nullableInt($data['match_id'] ?? null),
                        $providerId,
                        self::nullableInt($customerId),
                        self::nullableInt($req['primary_category_id'] ?? ($data['category_id'] ?? null)),
                        self::nullableInt($req['town_id'] ?? ($data['town_id'] ?? null)),
                        self::nullableInt($req['region_id'] ?? ($data['region_id'] ?? null)),
                        $status,
                        'inferred',
                        self::nullableTiny($data['used_via_vanassist'] ?? null),
                        $customerConfirmed,
                        $providerConfirmed,
                        $adminConfirmed,
                        self::nullableTiny($data['issue_resolved'] ?? null),
                        self::nullableTiny($data['would_use_again'] ?? null),
                        self::nullableInt($data['satisfaction_rating'] ?? null),
                        self::validValueBand($data['value_band'] ?? null),
                        isset($data['work_type']) ? substr((string) $data['work_type'], 0, 190) : null,
                        isset($data['cancellation_reason']) ? substr((string) $data['cancellation_reason'], 0, 255) : null,
                        isset($data['notes']) ? substr((string) $data['notes'], 0, 2000) : null,
                        $now,
                    ]
                );
            } else {
                $outcomeId = (int) $existing['id'];
                Database::query(
                    'UPDATE service_outcomes SET status = ?, '
                    . 'customer_confirmed = ?, provider_confirmed = ?, admin_confirmed = ?, '
                    . 'used_via_vanassist = COALESCE(?, used_via_vanassist), '
                    . 'issue_resolved = COALESCE(?, issue_resolved), would_use_again = COALESCE(?, would_use_again), '
                    . 'satisfaction_rating = COALESCE(?, satisfaction_rating), value_band = COALESCE(?, value_band), '
                    . 'work_type = COALESCE(?, work_type), cancellation_reason = COALESCE(?, cancellation_reason), '
                    . 'notes = COALESCE(?, notes), updated_at = NOW() WHERE id = ?',
                    [
                        $status, $customerConfirmed, $providerConfirmed, $adminConfirmed,
                        self::nullableTiny($data['used_via_vanassist'] ?? null),
                        self::nullableTiny($data['issue_resolved'] ?? null),
                        self::nullableTiny($data['would_use_again'] ?? null),
                        self::nullableInt($data['satisfaction_rating'] ?? null),
                        self::validValueBand($data['value_band'] ?? null),
                        isset($data['work_type']) ? substr((string) $data['work_type'], 0, 190) : null,
                        isset($data['cancellation_reason']) ? substr((string) $data['cancellation_reason'], 0, 255) : null,
                        isset($data['notes']) ? substr((string) $data['notes'], 0, 2000) : null,
                        $outcomeId,
                    ]
                );
            }

            self::stampStatusTimestamp($outcomeId, $status, $now);
            self::recomputeConfidence($outcomeId);
            self::detectRepeat($outcomeId, self::nullableInt($customerId), $providerId);

            // Audit trail entry for this confirmation.
            Database::query(
                'INSERT INTO outcome_confirmations (outcome_id, request_id, provider_id, confirmed_by_role, '
                . 'confirmed_by_user_id, confirmation_type, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $outcomeId,
                    $requestId > 0 ? $requestId : null,
                    $providerId,
                    in_array($role, ['customer', 'provider', 'admin', 'system'], true) ? $role : 'system',
                    self::nullableInt($data['by_user_id'] ?? null),
                    $status,
                    json_encode(['source' => $role, 'status' => $status], JSON_UNESCAPED_SLASHES) ?: null,
                ]
            );

            self::emitStatusEvent($status, $providerId, $requestId, $outcomeId);

            return $outcomeId;
        } catch (Throwable) {
            return null;
        }
    }

    /** Recompute the confidence level from the confirmation flags + status. */
    public static function recomputeConfidence(int $outcomeId): void
    {
        $o = Database::selectOne('SELECT status, customer_confirmed, provider_confirmed, admin_confirmed FROM service_outcomes WHERE id = ?', [$outcomeId]);
        if ($o === null) {
            return;
        }
        if ((int) $o['admin_confirmed'] === 1) {
            $confidence = 'admin_verified';
        } elseif ((int) $o['customer_confirmed'] === 1 && (int) $o['provider_confirmed'] === 1) {
            $confidence = 'both_confirmed';
        } elseif ((int) $o['provider_confirmed'] === 1) {
            $confidence = 'provider_reported';
        } elseif ((int) $o['customer_confirmed'] === 1) {
            $confidence = 'customer_reported';
        } elseif (in_array((string) $o['status'], ['contacted'], true)) {
            $confidence = 'contact_only';
        } else {
            $confidence = 'inferred';
        }
        Database::query('UPDATE service_outcomes SET confidence = ? WHERE id = ?', [$confidence, $outcomeId]);
    }

    /** Flag the outcome as a repeat if the customer used this provider before. */
    public static function detectRepeat(int $outcomeId, ?int $customerId, int $providerId): void
    {
        if ($customerId === null) {
            return;
        }
        $priors = (int) Database::scalar(
            'SELECT COUNT(*) FROM service_outcomes WHERE customer_id = ? AND provider_id = ? AND id <> ? '
            . "AND status IN ('booked','in_progress','completed') ",
            [$customerId, $providerId, $outcomeId]
        );
        if ($priors > 0) {
            Database::query('UPDATE service_outcomes SET is_repeat_provider = 1 WHERE id = ?', [$outcomeId]);
        }
    }

    /**
     * Persist a customer review for a confirmed outcome (one per outcome).
     * Reviews start pending and require moderation before publishing.
     */
    public static function submitReview(int $providerId, array $data): ?int
    {
        $rating = (int) ($data['rating'] ?? 0);
        if ($providerId === 0 || $rating < 1 || $rating > 5) {
            return null;
        }
        try {
            $outcomeId = self::nullableInt($data['outcome_id'] ?? null);
            if ($outcomeId !== null) {
                $requestId = self::nullableInt($data['request_id'] ?? null);
                $customerId = self::nullableInt($data['customer_id'] ?? null);
                $outcome = Database::selectOne(
                    'SELECT id FROM service_outcomes WHERE id = ? AND provider_id = ? '
                    . 'AND request_id <=> ? AND customer_id <=> ?',
                    [$outcomeId, $providerId, $requestId, $customerId]
                );
                if ($outcome === null) {
                    return null;
                }
            }

            $id = Database::insert(
                'INSERT INTO provider_reviews (provider_id, customer_id, request_id, outcome_id, rating, title, body, '
                . 'would_recommend, is_verified_use, status, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()) '
                . 'ON DUPLICATE KEY UPDATE rating = VALUES(rating), title = VALUES(title), body = VALUES(body), '
                . 'would_recommend = VALUES(would_recommend), updated_at = NOW()',
                [
                    $providerId,
                    self::nullableInt($data['customer_id'] ?? null),
                    self::nullableInt($data['request_id'] ?? null),
                    $outcomeId,
                    $rating,
                    isset($data['title']) ? substr((string) $data['title'], 0, 150) : null,
                    isset($data['body']) ? substr((string) $data['body'], 0, 4000) : null,
                    self::nullableTiny($data['would_recommend'] ?? null),
                    $outcomeId !== null ? 1 : 0,
                    'pending',
                ]
            );
            ActivityTracker::record('review_submitted', ['provider_id' => $providerId, 'outcome_id' => $outcomeId]);
            return $id;
        } catch (Throwable) {
            return null;
        }
    }

    public static function saveProvider(int $customerId, int $providerId): void
    {
        if ($customerId === 0 || $providerId === 0) {
            return;
        }
        try {
            Database::query(
                'INSERT IGNORE INTO saved_providers (customer_id, provider_id, created_at) VALUES (?, ?, NOW())',
                [$customerId, $providerId]
            );
            ActivityTracker::record('provider_saved', ['provider_id' => $providerId]);
        } catch (Throwable) {
        }
    }

    public static function unsaveProvider(int $customerId, int $providerId): void
    {
        if ($customerId === 0 || $providerId === 0) {
            return;
        }
        try {
            Database::query('DELETE FROM saved_providers WHERE customer_id = ? AND provider_id = ?', [$customerId, $providerId]);
            ActivityTracker::record('provider_unsaved', ['provider_id' => $providerId]);
        } catch (Throwable) {
        }
    }

    public static function providerMatchedToRequest(int $requestId, int $providerId): bool
    {
        if ($requestId <= 0 || $providerId <= 0) {
            return false;
        }

        return (int) Database::scalar(
            'SELECT COUNT(*) FROM service_request_matches WHERE request_id = ? AND provider_id = ?',
            [$requestId, $providerId]
        ) > 0;
    }

    // ---- internals ---------------------------------------------------------

    private static function stampStatusTimestamp(int $outcomeId, string $status, string $now): void
    {
        $col = match ($status) {
            'responded'  => 'responded_at',
            'selected'   => 'selected_at',
            'booked'     => 'booked_at',
            'completed'  => 'completed_at',
            'cancelled'  => 'cancelled_at',
            default      => null,
        };
        if ($col !== null) {
            Database::query("UPDATE service_outcomes SET {$col} = COALESCE({$col}, ?) WHERE id = ?", [$now, $outcomeId]);
        }
    }

    private static function emitStatusEvent(string $status, int $providerId, int $requestId, int $outcomeId): void
    {
        $map = [
            'responded' => 'provider_responded',
            'quoted'    => 'quote_received',
            'selected'  => 'provider_selected',
            'booked'    => 'job_booked',
            'completed' => 'job_completed',
            'cancelled' => 'job_cancelled',
            'outcome_unknown' => 'outcome_unknown',
        ];
        if (isset($map[$status])) {
            ActivityTracker::record($map[$status], [
                'provider_id' => $providerId,
                'request_id'  => $requestId > 0 ? $requestId : null,
                'outcome_id'  => $outcomeId,
            ]);
        }
    }

    private static function normaliseStatus(string $status): string
    {
        $valid = ['contacted', 'responded', 'quoted', 'selected', 'booked', 'in_progress', 'completed',
            'cancelled', 'unable_to_assist', 'outside_area', 'no_response', 'outcome_unknown'];
        return in_array($status, $valid, true) ? $status : 'contacted';
    }

    private static function validValueBand(mixed $v): ?string
    {
        $valid = ['under_100', '100_249', '250_499', '500_999', '1000_2499', '2500_4999', '5000_plus', 'prefer_not_say'];
        return in_array($v, $valid, true) ? (string) $v : null;
    }

    private static function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === 0 || $v === '0') {
            return null;
        }
        return (int) $v;
    }

    private static function nullableTiny(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        return $v ? 1 : 0;
    }
}
