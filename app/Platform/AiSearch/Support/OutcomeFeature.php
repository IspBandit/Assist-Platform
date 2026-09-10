<?php
declare(strict_types=1);
namespace App\Platform\AiSearch\Support;
use App\Services\FeatureFlag;
final class OutcomeFeature
{
    public const FLAG = 'assist_ai_outcomes';
    public static function enabled(): bool { return FeatureFlag::enabled(self::FLAG, false); }
}
