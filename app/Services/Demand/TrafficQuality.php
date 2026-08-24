<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Services\BotTraffic;

/**
 * Shared quality rules for first-party analytics.
 *
 * A same-brand navigation without the first-party session cookie cannot be a
 * measurable continuation of a normal browser visit. Counting each such hit
 * as a new visitor materially inflates acquisition, device and conversion
 * figures, so it is retained only as excluded search telemetry where supported.
 */
final class TrafficQuality
{
    public static function isUnattributableInternalNavigation(
        ?string $referralSource = null,
        ?bool $hadValidCookie = null,
    ): bool {
        if (PHP_SAPI === 'cli' && $referralSource === null && $hadValidCookie === null) {
            return false;
        }

        $source = $referralSource ?? TrackingSession::referralSource();
        $persisted = $hadValidCookie ?? TrackingSession::requestHadValidCookie();
        return $source === 'internal' && !$persisted;
    }

    public static function excludesCurrentRequest(): bool
    {
        if (PHP_SAPI === 'cli') {
            return false;
        }

        return TrackingSession::isBot()
            || BotTraffic::isSynthetic()
            || self::isUnattributableInternalNavigation();
    }

    /** SQL predicate for an attributable, non-bot tracking_sessions alias. */
    public static function eligibleSessionSql(string $alias = 'ts'): string
    {
        if (preg_match('/^[a-z][a-z0-9_]*$/i', $alias) !== 1) {
            throw new \InvalidArgumentException('Invalid SQL alias.');
        }

        return "{$alias}.is_bot=0 AND {$alias}.is_excluded=0 "
            . "AND NOT ({$alias}.referral_source='internal' "
            . "AND {$alias}.user_id IS NULL AND {$alias}.customer_id IS NULL "
            . "AND {$alias}.first_seen_at={$alias}.last_seen_at)";
    }
}
