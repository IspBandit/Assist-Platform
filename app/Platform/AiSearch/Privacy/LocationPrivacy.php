<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Privacy;

/**
 * Location privacy helpers for Assist AI analytics (AI-7).
 * Precise GPS must not be retained in long-lived NL analytics tables.
 */
final class LocationPrivacy
{
    /**
     * Round coordinates to ~1.1 km grid (2 decimal places) for derived analytics.
     *
     * @return array{0:float,1:float}|null
     */
    public static function roundCoords(?float $lat, ?float $lng, int $decimals = 2): ?array
    {
        if ($lat === null || $lng === null) {
            return null;
        }
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }
        $decimals = max(0, min(5, $decimals));
        return [
            round($lat, $decimals),
            round($lng, $decimals),
        ];
    }

    /**
     * Assist search logging stores town_id + precision label only — never raw GPS.
     */
    public static function allowedAssistSearchLocationFields(): array
    {
        return ['town_id', 'radius_km', 'location_precision'];
    }
}
