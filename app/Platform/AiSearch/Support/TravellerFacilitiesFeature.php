<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Support;

use App\Services\FeatureFlag;

/**
 * Feature gate for dedicated traveller_facilities search (AI-6 / ADR 0032).
 */
final class TravellerFacilitiesFeature
{
    public const FLAG = 'assist_ai_traveller_facilities';

    public static function enabled(): bool
    {
        return FeatureFlag::enabled(self::FLAG, false);
    }
}
