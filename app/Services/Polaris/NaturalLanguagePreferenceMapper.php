<?php

declare(strict_types=1);

namespace App\Services\Polaris;

/**
 * Deterministic natural-language → PreferenceProfile hints.
 * Does not call AI. Unrecognised text leaves defaults and sets low confidence.
 */
final class NaturalLanguagePreferenceMapper
{
    /**
     * @return array{profile:PreferenceProfile,hints:list<string>,confidence:float,tow_query:?string}
     */
    public static function map(string $prompt, ?PreferenceProfile $base = null): array
    {
        $text = strtolower(trim($prompt));
        $baseArr = $base?->toArray() ?? PreferenceProfile::fromArray([])->toArray();
        $hints = [];
        $towQuery = null;

        // Family / travellers
        if (preg_match('/\b(couple|two adults|2 adults)\b/', $text)) {
            $baseArr['adults'] = 2;
            $baseArr['children'] = 0;
            $hints[] = 'Detected couple travelling.';
        }
        if (preg_match('/\b(family|kids|children|bunks)\b/', $text)) {
            $baseArr['adults'] = max(2, (int) ($baseArr['adults'] ?? 2));
            $baseArr['children'] = max(2, (int) ($baseArr['children'] ?? 0));
            $baseArr['min_sleeps'] = max(4, (int) ($baseArr['min_sleeps'] ?? 0));
            $hints[] = 'Detected family / bunks need.';
        }
        if (preg_match('/\b(solo|single traveller)\b/', $text)) {
            $baseArr['adults'] = 1;
            $baseArr['children'] = 0;
            $hints[] = 'Detected solo traveller.';
        }

        // Budget
        if (preg_match('/\$?\s*(\d{2,3})\s*,\s*(\d{3})\b/', $text, $m)) {
            $baseArr['max_budget_aud'] = ((int) $m[1] * 1000) + (int) $m[2];
            $hints[] = 'Detected budget around AUD ' . number_format((int) $baseArr['max_budget_aud']) . '.';
        } elseif (preg_match('/(?:under|below|max(?:imum)?|budget)\s*(?:of\s*)?\$?\s*(\d{2,3})\s*k\b/', $text, $m)) {
            $baseArr['max_budget_aud'] = ((int) $m[1]) * 1000;
            $hints[] = 'Detected budget under AUD ' . number_format((int) $baseArr['max_budget_aud']) . '.';
        } elseif (preg_match('/(?:under|below|max(?:imum)?|budget)\s*(?:of\s*)?\$?\s*(\d{4,7})\b/', $text, $m)) {
            $baseArr['max_budget_aud'] = (int) $m[1];
            $hints[] = 'Detected budget under AUD ' . number_format((int) $baseArr['max_budget_aud']) . '.';
        }

        // Off-grid
        if (preg_match('/\b(free\s*camp(?:ing)?|off[- ]grid|boondock)\b/', $text)) {
            $baseArr['off_grid_nights'] = max(5, (int) ($baseArr['off_grid_nights'] ?? 0));
            $baseArr['priority_off_grid'] = 'essential';
            $hints[] = 'Detected free-camping / off-grid priority.';
        }
        if (preg_match('/\b(week|7\s*days|seven days)\b/', $text)
            && preg_match('/\b(free\s*camp(?:ing)?|off[- ]grid|camp(?:ing)?)\b/', $text)
        ) {
            $baseArr['off_grid_nights'] = max(7, (int) ($baseArr['off_grid_nights'] ?? 0));
            $hints[] = 'Detected about a week off-grid.';
        }
        if (preg_match('/\b(caravan park|powered site|mostly parks)\b/', $text)) {
            $baseArr['off_grid_nights'] = min(2, (int) ($baseArr['off_grid_nights'] ?? 0));
            $hints[] = 'Detected mostly powered / park stays.';
        }

        // Weight / lightweight
        if (preg_match('/\b(lightweight|light weight|under\s*2,?000\s*kg|2000\s*kg)\b/', $text)) {
            $baseArr['max_atm_kg'] = 2000;
            $baseArr['priority_towability'] = 'essential';
            $hints[] = 'Detected lightweight / ATM cap near 2,000 kg.';
        }
        if (preg_match('/\batm\s*(?:under|below|<)?\s*(\d{3,4})\b/', $text, $m)) {
            $baseArr['max_atm_kg'] = (int) $m[1];
            $hints[] = 'Detected ATM limit ' . (int) $m[1] . ' kg.';
        }

        // Facilities
        if (preg_match('/\b(ensuite|bathroom|toilet|shower)\b/', $text)) {
            $baseArr['require_bathroom'] = true;
            $hints[] = 'Detected bathroom as essential.';
        }

        // Categories
        $categories = [];
        if (preg_match('/\bhybrid\b/', $text)) {
            $categories[] = 'hybrid_caravan';
        }
        if (preg_match('/\bcamper\s*trailer\b/', $text)) {
            $categories[] = 'camper_trailer';
        }
        if (preg_match('/\bmotorhome\b/', $text)) {
            $categories[] = 'motorhome';
        }
        if (preg_match('/\bcampervan\b/', $text)) {
            $categories[] = 'campervan';
        }
        if (preg_match('/\bslide[- ]?on\b/', $text)) {
            $categories[] = 'slide_on';
        }
        if (preg_match('/\bcaravan\b/', $text) && $categories === []) {
            $categories[] = 'caravan';
        }
        if ($categories !== []) {
            $baseArr['categories'] = $categories;
            $hints[] = 'Detected category focus: ' . implode(', ', $categories) . '.';
        }

        // Tow vehicle hint (for Tow Match deep-link; not authoritative)
        if (preg_match('/\b(prado\s*250|landcruiser\s*300|land ?cruiser|ranger|hilux|patrol|triton|colorado|amarok|everest|mux|d[- ]?max)\b/i', $prompt, $m)) {
            $towQuery = trim($m[1]);
            $baseArr['priority_towability'] = 'essential';
            $hints[] = 'Detected tow vehicle mention: ' . $towQuery . '.';
        }

        // Convert nested priorities from toArray shape for fromArray
        if (isset($baseArr['priorities']) && is_array($baseArr['priorities'])) {
            foreach ($baseArr['priorities'] as $key => $value) {
                $baseArr['priority_' . $key] = $value;
            }
        }
        if (isset($baseArr['max_budget_aud_cents']) && !isset($baseArr['max_budget_aud'])) {
            $baseArr['max_budget_aud'] = (int) round(((int) $baseArr['max_budget_aud_cents']) / 100);
        }

        $confidence = $hints === [] ? 0.15 : min(0.9, 0.35 + (0.08 * count($hints)));
        return [
            'profile' => PreferenceProfile::fromArray($baseArr),
            'hints' => $hints,
            'confidence' => $confidence,
            'tow_query' => $towQuery,
        ];
    }
}
