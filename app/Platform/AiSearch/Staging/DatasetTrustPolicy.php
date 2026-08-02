<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Staging;

/**
 * Trust policies for staging external hits (ADR 0025 / 0026).
 * No trusted_automatic without a written owner decision.
 */
final class DatasetTrustPolicy
{
    public const TRUSTED_AUTOMATIC = 'trusted_automatic';
    public const TRUSTED_REVIEW = 'trusted_review';
    public const COMMUNITY_REVIEW = 'community_review';
    public const WEB_RESEARCH_REVIEW = 'web_research_review';
    public const PROHIBITED = 'prohibited';

    /** Connectors that may never be called from Ask VanAssist live search. */
    public const ASK_BLOCKED_CONNECTORS = [
        'google_places',
    ];

    public static function mayDisplayLive(string $policy): bool
    {
        return in_array($policy, [self::TRUSTED_REVIEW, self::COMMUNITY_REVIEW], true);
    }

    public static function mayStage(string $policy): bool
    {
        return in_array($policy, [self::TRUSTED_REVIEW, self::COMMUNITY_REVIEW], true);
    }

    public static function isKnown(string $policy): bool
    {
        return in_array($policy, [
            self::TRUSTED_AUTOMATIC,
            self::TRUSTED_REVIEW,
            self::COMMUNITY_REVIEW,
            self::WEB_RESEARCH_REVIEW,
            self::PROHIBITED,
        ], true);
    }

    public static function mayAutoPublish(string $policy): bool
    {
        // Explicitly never true until owner writes a trusted_automatic decision.
        unset($policy);
        return false;
    }

    public static function isAskBlockedConnector(string $connectorKey): bool
    {
        return in_array($connectorKey, self::ASK_BLOCKED_CONNECTORS, true);
    }
}
