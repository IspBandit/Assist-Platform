<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Database;
use App\Services\FeatureFlag;
use Throwable;

/**
 * Central writer for the demand-to-outcome funnel tables. Controllers and the
 * cron runner call these helpers instead of writing to the analytics tables
 * directly, so dedupe rules, exclusion and the confidence model live in one
 * place.
 *
 * Every method is a no-op unless the demand_analytics flag is on, and every
 * path is wrapped so an analytics failure can never break the customer journey
 * (requirement sections 7 & 25). ActivityTracker handles the granular event
 * stream; this service owns the relational records (searches, impressions,
 * contact actions, outcomes, confirmations, demand-gap feedback).
 */
final class DemandRecorder
{
    /** Suppress duplicate contact actions / profile views within this window. */
    private const DEDUPE_SECONDS = 30;

    public static function enabled(): bool
    {
        try {
            return FeatureFlag::enabled(ActivityTracker::FLAG, false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Record one meaningful provider search. Returns the new provider_searches
     * id (so impressions and the completion event can be attributed), or null.
     *
     * @param array<string,mixed> $p
     */
    public static function recordSearch(array $p): ?int
    {
        if (!self::enabled() || ActivityTracker::excluded()) {
            return null;
        }
        try {
            $sessionId = TrackingSession::id();
            $searchId = Database::insert(
                'INSERT INTO provider_searches '
                . '(session_id, user_id, request_id, town_id, region_id, state_id, postcode, category_id, '
                . 'subcategory_id, urgency, service_type, radius_km, result_count, exact_match_count, '
                . 'used_nearby_fallback, radius_expanded, led_to_request, is_excluded, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, NOW())',
                [
                    $sessionId,
                    self::nullableInt($p['user_id'] ?? null),
                    self::nullableInt($p['request_id'] ?? null),
                    self::nullableInt($p['town_id'] ?? null),
                    self::nullableInt($p['region_id'] ?? null),
                    self::nullableInt($p['state_id'] ?? null),
                    isset($p['postcode']) && $p['postcode'] !== '' ? substr((string) $p['postcode'], 0, 10) : null,
                    self::nullableInt($p['category_id'] ?? null),
                    self::nullableInt($p['subcategory_id'] ?? null),
                    in_array($p['urgency'] ?? null, ['low', 'medium', 'high', 'urgent'], true) ? $p['urgency'] : null,
                    in_array($p['service_type'] ?? null, ['mobile', 'workshop', 'either', 'roadside', 'park_callout'], true) ? $p['service_type'] : null,
                    self::nullableInt($p['radius_km'] ?? null),
                    (int) ($p['result_count'] ?? 0),
                    (int) ($p['exact_match_count'] ?? ($p['result_count'] ?? 0)),
                    !empty($p['used_nearby_fallback']) ? 1 : 0,
                    !empty($p['radius_expanded']) ? 1 : 0,
                ]
            );

            ActivityTracker::record(
                (int) ($p['result_count'] ?? 0) > 0 ? 'provider_search_completed' : 'no_provider_found',
                [
                    'search_id'   => $searchId,
                    'category_id' => $p['category_id'] ?? null,
                    'town_id'     => $p['town_id'] ?? null,
                    'region_id'   => $p['region_id'] ?? null,
                    'metadata'    => ['results' => (int) ($p['result_count'] ?? 0)],
                ]
            );

            return $searchId;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Record the provider results shown for a search as impressions, deduped by
     * the (search_id, provider_id) unique key.
     *
     * @param array<int,array<string,mixed>> $providers Ordered as displayed.
     */
    public static function recordImpressions(?int $searchId, array $providers, ?int $categoryId = null): void
    {
        if ($searchId === null || !self::enabled() || $providers === []) {
            return;
        }
        try {
            $rank = 0;
            foreach ($providers as $row) {
                $rank++;
                $providerId = (int) ($row['id'] ?? 0);
                if ($providerId === 0) {
                    continue;
                }
                Database::query(
                    'INSERT IGNORE INTO provider_search_results '
                    . '(search_id, provider_id, rank_position, match_score, distance_km, is_organic, '
                    . 'is_sponsored, is_verified, is_available, service_model, category_id, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, 1, 0, ?, 1, ?, ?, NOW())',
                    [
                        $searchId,
                        $providerId,
                        $rank,
                        isset($row['match_score']) ? (float) $row['match_score'] : null,
                        isset($row['distance_km']) ? (float) $row['distance_km'] : null,
                        !empty($row['is_verified']) ? 1 : 0,
                        in_array($row['service_model'] ?? null, ['mobile', 'workshop', 'both'], true) ? $row['service_model'] : null,
                        $categoryId,
                    ]
                );
            }
        } catch (Throwable) {
            // Never block rendering on impression recording.
        }
    }

    /** Mark that a search later produced a customer request. */
    public static function markSearchLedToRequest(?int $searchId, ?int $requestId): void
    {
        if ($searchId === null || !self::enabled()) {
            return;
        }
        try {
            Database::query(
                'UPDATE provider_searches SET led_to_request = 1, request_id = COALESCE(request_id, ?) WHERE id = ?',
                [self::nullableInt($requestId), $searchId]
            );
        } catch (Throwable) {
        }
    }

    /**
     * Record an attributable provider contact action (phone/email/website/
     * directions/message/quote/assistance/booking), deduped per session.
     */
    public static function recordContactAction(int $providerId, string $actionType, array $ctx = []): void
    {
        $valid = ['phone', 'email', 'website', 'directions', 'message', 'quote_request', 'assistance_request', 'booking_request'];
        if ($providerId === 0 || !in_array($actionType, $valid, true) || !self::enabled() || ActivityTracker::excluded()) {
            return;
        }
        try {
            $sessionId = TrackingSession::id();

            if ($sessionId !== null) {
                $recent = (int) Database::scalar(
                    'SELECT COUNT(*) FROM provider_contact_actions '
                    . 'WHERE provider_id = ? AND action_type = ? AND session_id = ? '
                    . 'AND created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)',
                    [$providerId, $actionType, $sessionId, self::DEDUPE_SECONDS]
                );
                if ($recent > 0) {
                    return;
                }
            }

            Database::query(
                'INSERT INTO provider_contact_actions '
                . '(provider_id, session_id, user_id, request_id, search_id, match_id, action_type, source_route, is_excluded, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())',
                [
                    $providerId,
                    $sessionId,
                    self::nullableInt($ctx['user_id'] ?? null),
                    self::nullableInt($ctx['request_id'] ?? null),
                    self::nullableInt($ctx['search_id'] ?? null),
                    self::nullableInt($ctx['match_id'] ?? null),
                    $actionType,
                    isset($ctx['route']) ? substr((string) $ctx['route'], 0, 190) : null,
                ]
            );

            $eventMap = [
                'phone'      => 'provider_phone_clicked',
                'email'      => 'provider_email_clicked',
                'website'    => 'provider_website_clicked',
                'directions' => 'provider_directions_clicked',
                'message'    => 'provider_message_started',
                'assistance_request' => 'provider_request_sent',
            ];
            if (isset($eventMap[$actionType])) {
                ActivityTracker::record($eventMap[$actionType], [
                    'provider_id' => $providerId,
                    'request_id'  => $ctx['request_id'] ?? null,
                    'search_id'   => $ctx['search_id'] ?? null,
                ]);
            }
        } catch (Throwable) {
        }
    }

    /** Record a deliberate provider profile view (deduped per session). */
    public static function recordProfileView(int $providerId): void
    {
        if ($providerId === 0 || !self::enabled() || ActivityTracker::excluded()) {
            return;
        }
        try {
            ActivityTracker::record('provider_profile_viewed', ['provider_id' => $providerId]);
        } catch (Throwable) {
        }
    }

    /** Record structured "why no suitable provider" feedback. */
    public static function recordDemandGap(string $reason, array $ctx = []): void
    {
        $valid = ['none_nearby', 'none_soon_enough', 'no_mobile', 'no_workshop', 'outside_area',
            'wrong_category', 'could_not_assist', 'price', 'no_contact', 'no_response', 'licensing',
            'found_elsewhere', 'other'];
        if (!in_array($reason, $valid, true) || !self::enabled()) {
            return;
        }
        try {
            Database::query(
                'INSERT INTO demand_gap_feedback '
                . '(session_id, user_id, request_id, search_id, town_id, region_id, category_id, reason, comment, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    TrackingSession::id(),
                    self::nullableInt($ctx['user_id'] ?? null),
                    self::nullableInt($ctx['request_id'] ?? null),
                    self::nullableInt($ctx['search_id'] ?? null),
                    self::nullableInt($ctx['town_id'] ?? null),
                    self::nullableInt($ctx['region_id'] ?? null),
                    self::nullableInt($ctx['category_id'] ?? null),
                    $reason,
                    isset($ctx['comment']) && $ctx['comment'] !== '' ? substr((string) $ctx['comment'], 0, 500) : null,
                ]
            );
            ActivityTracker::record('demand_gap_reported', [
                'request_id'  => $ctx['request_id'] ?? null,
                'category_id' => $ctx['category_id'] ?? null,
                'town_id'     => $ctx['town_id'] ?? null,
                'metadata'    => ['reason' => $reason],
            ]);
        } catch (Throwable) {
        }
    }

    private static function nullableInt(mixed $v): ?int
    {
        if ($v === null || $v === '' || $v === 0 || $v === '0') {
            return null;
        }
        return (int) $v;
    }
}
