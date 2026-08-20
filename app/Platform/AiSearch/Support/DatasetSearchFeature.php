<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Support;

use App\Services\FeatureFlag;

/**
 * Feature gate for dataset / staged-candidate routing (AI-5).
 * Independent of paid AI; never enables Google Places from Ask VanAssist.
 */
final class DatasetSearchFeature
{
    public const FLAG = 'assist_ai_datasets';

    public static function enabled(): bool
    {
        return FeatureFlag::enabled(self::FLAG, false);
    }
}
