<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Support;

use App\Services\FeatureFlag;

/**
 * Demand-driven Google Places rescue on zero/weak provider searches (ADR 0042).
 * Off by default until Control Centre credentials, budget and Quality Gate.
 */
final class PlacesRescueFeature
{
    public const FLAG = 'provider_places_rescue';

    public static function enabled(): bool
    {
        return FeatureFlag::enabled(self::FLAG, false);
    }
}
