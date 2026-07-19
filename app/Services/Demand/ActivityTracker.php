<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Auth\Auth;
use App\Core\Config;
use App\Core\Database;
use App\Services\FeatureFlag;
use Throwable;

/**
 * Central, first-party demand-funnel event recorder.
 *
 * Templates and controllers never write to analytics tables directly - they
 * call ActivityTracker::record() with a validated event name and a small
 * structured context. The funnel stages this captures are:
 *
 *   need -> search -> impression -> profile view -> contact action ->
 *   request -> response -> quote -> selection -> booking -> completion.
 *
 * Guarantees:
 *  - No-op unless the demand_analytics feature flag is enabled (zero overhead
 *    and zero risk by default; rollout is a single flag toggle).
 *  - Unknown event names are rejected so reporting stays consistent.
 *  - Every path is wrapped so a tracking failure can NEVER break the page or
 *    stop a customer contacting a provider (requirement sections 7 & 25).
 *  - Bots, admin-preview and excluded sessions are dropped from recording.
 *  - Sensitive free text (descriptions, contact details) is NOT copied into
 *    event metadata; it already lives in the secure service_requests record.
 */
final class ActivityTracker
{
    public const FLAG = 'demand_analytics';

    /**
     * The complete, validated event vocabulary. Keep in sync with
     * docs/DEMAND-ANALYTICS.md and the metric definitions in section 23.
     *
     * @var array<int,string>
     */
    public const EVENTS = [
        // Location
        'location_prompt_displayed', 'location_permission_granted', 'location_permission_denied',
        'location_manually_selected', 'location_changed',
        // Category / need form
        'category_viewed', 'category_selected', 'subcategory_selected',
        'need_form_started', 'need_form_step_completed', 'need_form_abandoned', 'need_submitted',
        // Search / impressions
        'provider_search_completed', 'provider_impression', 'provider_profile_viewed',
        'no_provider_found', 'search_radius_expanded', 'nearby_provider_selected',
        // Contact actions
        'provider_phone_clicked', 'provider_email_clicked', 'provider_website_clicked',
        'provider_directions_clicked', 'provider_message_started', 'provider_request_sent',
        'provider_saved', 'provider_unsaved',
        // Provider / outcome lifecycle
        'provider_responded', 'quote_received', 'provider_selected',
        'job_booked', 'job_completed', 'job_cancelled', 'outcome_unknown',
        'review_requested', 'review_submitted',
        // Demand-gap feedback
        'demand_gap_reported',
    ];

    /** Columns on analytics_events that callers may set via context. */
    private const ALLOWED = [
        'session_id', 'user_id', 'request_id', 'provider_id', 'category_id',
        'town_id', 'region_id', 'search_id', 'match_id', 'outcome_id',
        'previous_stage', 'route',
    ];

    public static function enabled(): bool
    {
        // Unit tests and pre-install requests may not have a configured
        // database. Feature-flag lookup must remain a true no-op in that state.
        if (trim((string) Config::get('database.name', '')) === '') {
            return false;
        }

        try {
            return FeatureFlag::enabled(self::FLAG, false);
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Record a funnel event. Returns the inserted id, or null if skipped.
     *
     * @param array<string,mixed> $context Subset of ALLOWED columns; plus an
     *                                      optional 'metadata' array (small,
     *                                      non-sensitive scalars only).
     */
    public static function record(string $eventName, array $context = []): ?int
    {
        try {
            if (!self::enabled() || !in_array($eventName, self::EVENTS, true)) {
                return null;
            }
            if (self::excluded()) {
                return null;
            }

            $sessionId = $context['session_id'] ?? TrackingSession::id();
            $userId = $context['user_id'] ?? self::currentUserId();

            $columns = ['event_name'];
            $values = [$eventName];
            $columns[] = 'session_id';
            $values[] = $sessionId !== null ? (int) $sessionId : null;
            $columns[] = 'user_id';
            $values[] = $userId !== null ? (int) $userId : null;

            foreach (self::ALLOWED as $col) {
                if ($col === 'session_id' || $col === 'user_id') {
                    continue;
                }
                if (array_key_exists($col, $context) && $context[$col] !== null && $context[$col] !== '') {
                    $columns[] = $col;
                    $values[] = in_array($col, ['previous_stage', 'route'], true)
                        ? substr((string) $context[$col], 0, 190)
                        : (int) $context[$col];
                }
            }

            $columns[] = 'device_type';
            $values[] = TrackingSession::deviceType();
            $columns[] = 'referral_source';
            $values[] = TrackingSession::referralSource();

            if (!empty($context['metadata']) && is_array($context['metadata'])) {
                $columns[] = 'metadata';
                $values[] = self::encodeMetadata($context['metadata']);
            }

            $columns[] = 'created_at';
            $placeholders = array_fill(0, count($columns), '?');
            $placeholders[count($placeholders) - 1] = 'NOW()';
            array_pop($values); // created_at uses NOW(), no bound value

            $sql = 'INSERT INTO analytics_events (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $placeholders) . ')';
            return Database::insert($sql, $values);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Exclude traffic that would distort customer-facing metrics: bots, and
     * signed-in administrators/staff previewing the public site.
     */
    public static function excluded(): bool
    {
        try {
            if (TrackingSession::isBot()) {
                return true;
            }
            $auth = Auth::instance();
            if ($auth->check() && $auth->hasAnyRole('administrator', Auth::SUPER_ADMIN, 'moderator')) {
                return true;
            }
        } catch (Throwable) {
            // Fall through - default to not excluded.
        }
        return false;
    }

    private static function encodeMetadata(array $metadata): ?string
    {
        $clean = [];
        foreach ($metadata as $key => $value) {
            if (is_scalar($value) || $value === null) {
                $clean[substr((string) $key, 0, 40)] = is_string($value) ? substr($value, 0, 190) : $value;
            }
        }
        $json = json_encode($clean, JSON_UNESCAPED_SLASHES);
        return $json === false ? null : $json;
    }

    private static function currentUserId(): ?int
    {
        try {
            $auth = Auth::instance();
            return $auth->check() ? $auth->id() : null;
        } catch (Throwable) {
            return null;
        }
    }
}
