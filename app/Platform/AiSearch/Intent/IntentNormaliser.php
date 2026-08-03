<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Intent;

/**
 * Normalises free-text queries for deterministic matching and logging.
 */
final class IntentNormaliser
{
    /**
     * @return array{
     *   normalised:string,
     *   use_current_location:bool,
     *   radius_km:?int,
     *   urgency:string,
     *   remainder:string
     * }
     */
    public static function analyse(string $raw): array
    {
        $text = trim(mb_strtolower($raw));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = str_replace(['tyre', 'tyres'], ['tire', 'tires'], $text); // unify then map in rules
        // Prefer AU spelling in remainder for display; matching uses both forms in rules.

        $useCurrent = false;
        if (preg_match('/\b(near me|nearby|closest|nearest|around me|current location)\b/u', $text) === 1) {
            $useCurrent = true;
            $text = trim((string) preg_replace('/\b(near me|nearby|closest|nearest|around me|current location)\b/u', ' ', $text));
        }

        $radiusKm = null;
        if (preg_match('/\bwithin\s+(\d{1,3})\s*(km|kilometres|kilometers)?\b/u', $text, $m) === 1
            || preg_match('/\b(\d{1,3})\s*(km|kilometres|kilometers)\b/u', $text, $m) === 1) {
            $radiusKm = max(1, min(500, (int) $m[1]));
            $text = trim((string) preg_replace('/\bwithin\s+\d{1,3}\s*(km|kilometres|kilometers)?\b/u', ' ', $text));
            $text = trim((string) preg_replace('/\b\d{1,3}\s*(km|kilometres|kilometers)\b/u', ' ', $text));
        }

        $urgency = 'normal';
        if (preg_match('/\b(urgent|asap|stranded|broken down|breakdown)\b/u', $text) === 1) {
            $urgency = 'urgent';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        // Restore AU tyre spelling for normalised storage after radius strip.
        $normalised = str_replace(['tire', 'tires'], ['tyre', 'tyres'], $text);

        return [
            'normalised' => $normalised,
            'use_current_location' => $useCurrent,
            'radius_km' => $radiusKm,
            'urgency' => $urgency,
            'remainder' => $normalised,
        ];
    }
}
