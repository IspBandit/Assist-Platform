<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Support;

use App\Services\FeatureFlag;

/**
 * Feature gate for Ask VanAssist / Assist AI Search.
 */
final class AiSearchFeature
{
    public const FLAG = 'assist_ai_search';

    public static function enabled(): bool
    {
        return FeatureFlag::enabled(self::FLAG, false);
    }
}
