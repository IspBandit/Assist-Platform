<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Intent;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;

/**
 * Deterministic keyword/pattern intent engine (intent_rules_v1).
 * No paid AI. Always returns a structured Intent.
 */
final class IntentRuleEngine
{
    public const VERSION = 'intent_rules_v2';

    /**
     * @var list<array{
     *   id:string,
     *   patterns:list<string>,
     *   intent_type:string,
     *   provider_category_keys:list<string>,
     *   facility_type_keys:list<string>,
     *   stay_type_keys:list<string>,
     *   adapter_keys:list<string>,
     *   confidence:float
     * }>
     */
    private const RULES = [
        [
            'id' => 'R02',
            'patterns' => ['dump point', 'dump points', 'sanitary dump', 'cassette dump', 'sullage'],
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => ['dump-points'],
            'facility_type_keys' => ['dump_point'],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers', 'traveller_facilities'],
            'confidence' => 0.92,
        ],
        [
            'id' => 'R03',
            'patterns' => ['drinking water', 'potable water', 'water refill', 'tank water'],
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => ['potable-water-refill'],
            'facility_type_keys' => ['drinking_water'],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers', 'traveller_facilities'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R01',
            'patterns' => ['public toilet', 'public toilets', 'toilet', 'toilets', 'loo', 'restroom'],
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => [],
            'facility_type_keys' => ['public_toilet'],
            'stay_type_keys' => [],
            'adapter_keys' => ['traveller_facilities'],
            'confidence' => 0.85,
        ],
        [
            'id' => 'R04',
            'patterns' => ['lpg', 'gas bottle', 'gas refill', 'bottle exchange', 'swap cylinder'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['lpg-refills-and-bottle-exchange'],
            'facility_type_keys' => ['lpg_refill'],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.92,
        ],
        [
            'id' => 'R05',
            'patterns' => ['caravan park', 'caravan parks', 'holiday park'],
            'intent_type' => Intent::TYPE_STAY,
            'provider_category_keys' => ['caravan-parks-and-campgrounds'],
            'facility_type_keys' => [],
            'stay_type_keys' => ['caravan_park'],
            'adapter_keys' => ['stays'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R06',
            'patterns' => ['free camp', 'free camping', 'low cost camp', 'low-cost camp'],
            'intent_type' => Intent::TYPE_STAY,
            'provider_category_keys' => ['free-and-low-cost-camps'],
            'facility_type_keys' => [],
            'stay_type_keys' => ['free_camp'],
            'adapter_keys' => ['stays'],
            'confidence' => 0.88,
        ],
        [
            'id' => 'R07',
            'patterns' => ['campground', 'camping ground'],
            'intent_type' => Intent::TYPE_STAY,
            'provider_category_keys' => [],
            'facility_type_keys' => [],
            'stay_type_keys' => ['campground'],
            'adapter_keys' => ['stays'],
            'confidence' => 0.85,
        ],
        [
            'id' => 'R08',
            'patterns' => ['rest area', 'rest areas'],
            'intent_type' => Intent::TYPE_STAY,
            'provider_category_keys' => ['rest-areas-and-rv-friendly-parking'],
            'facility_type_keys' => ['rest_area'],
            'stay_type_keys' => ['rest_area'],
            'adapter_keys' => ['stays'],
            'confidence' => 0.75,
        ],
        [
            'id' => 'R09',
            'patterns' => ['mobile caravan repair', 'mobile rv repair', 'mobile caravan repairer'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['general-caravan-repairs', 'mobile-mechanics'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.88,
        ],
        [
            'id' => 'R10',
            'patterns' => ['auto electrician', 'auto electrical', 'solar panel', 'solar panels', 'solar not working', '12 volt', '12v electrical'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['auto-electrical-and-batteries'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.92,
        ],
        [
            'id' => 'R11',
            'patterns' => ['tyre', 'tyres', 'tire', 'tires', 'puncture'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['tyres-and-wheels'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R12',
            'patterns' => ['tow truck', 'vehicle recovery', 'towing', 'tow'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['towing-and-vehicle-recovery'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R13',
            'patterns' => ['caravan brake', 'caravan brakes', 'brakes and bearings', 'wheel bearing', 'wheel bearings'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['brakes-and-bearings'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R15',
            'patterns' => ['diesel mechanic', 'diesel mechanics'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['diesel-mechanics'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R14',
            'patterns' => ['mobile mechanic', 'mechanic'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['mobile-mechanics', 'mechanical-repairs'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.8,
        ],
        [
            'id' => 'R16',
            'patterns' => ['ev charger', 'ev charging', 'electric vehicle charging'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['ev-charging'],
            'facility_type_keys' => ['ev_charging'],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.88,
        ],
        [
            'id' => 'R17',
            'patterns' => ['petrol', 'servo', 'fuel stop', 'fuel'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['fuel-and-travel-stops'],
            'facility_type_keys' => ['fuel'],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.82,
        ],
        [
            'id' => 'R18',
            'patterns' => ['weighbridge', 'weigh bridge'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['weighbridges-and-mobile-weighing'],
            'facility_type_keys' => ['weighbridge'],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.9,
        ],
        [
            'id' => 'R19',
            'patterns' => ['roadside assistance', 'breakdown'],
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['roadside-assistance'],
            'facility_type_keys' => [],
            'stay_type_keys' => [],
            'adapter_keys' => ['providers'],
            'confidence' => 0.88,
        ],
    ];

    public function interpret(string $rawQuery): Intent
    {
        $meta = IntentNormaliser::analyse($rawQuery);
        $haystack = $meta['remainder'];
        // Matching haystack also keeps US tire spelling variants.
        $matchText = str_replace(['tyre', 'tyres'], ['tire', 'tires'], $haystack);
        $matchText = $haystack . ' ' . $matchText;

        $hits = [];
        foreach (self::RULES as $rule) {
            foreach ($rule['patterns'] as $pattern) {
                $patternNorm = mb_strtolower($pattern);
                if ($this->containsPhrase($matchText, $patternNorm) || $this->containsPhrase($haystack, $patternNorm)) {
                    $hits[$rule['id']] = $rule;
                    break;
                }
            }
        }

        if ($hits === []) {
            return new Intent(
                intentType: Intent::TYPE_UNKNOWN,
                providerCategoryKeys: [],
                stayTypeKeys: [],
                facilityTypeKeys: [],
                locationText: $this->extractLocationText($haystack, []),
                useCurrentLocation: $meta['use_current_location'],
                radiusKm: $meta['radius_km'] ?? (int) config('ai_search.default_radius_km', 25),
                urgency: $meta['urgency'],
                adapterKeys: [],
                confidence: 0.0,
                clarificationRequired: true,
                clarificationReason: 'Could not determine what you need. Try choosing a category in Find a service, or rephrase (for example: dump point near Batemans Bay).',
                source: 'rules',
            );
        }

        $providerKeys = [];
        $stayKeys = [];
        $facilityKeys = [];
        $adapterKeys = [];
        $types = [];
        $confidence = 1.0;
        $matchedPatterns = [];

        foreach ($hits as $rule) {
            $types[$rule['intent_type']] = true;
            $providerKeys = array_merge($providerKeys, $rule['provider_category_keys']);
            $stayKeys = array_merge($stayKeys, $rule['stay_type_keys']);
            $facilityKeys = array_merge($facilityKeys, $rule['facility_type_keys']);
            $adapterKeys = array_merge($adapterKeys, $rule['adapter_keys']);
            $confidence = min($confidence, (float) $rule['confidence']);
            $matchedPatterns = array_merge($matchedPatterns, $rule['patterns']);
        }

        $intentType = count($types) === 1
            ? array_key_first($types)
            : Intent::TYPE_MIXED;
        if (count($hits) > 1) {
            $confidence = max(0.0, $confidence - 0.1);
            if (count($types) > 1) {
                $intentType = Intent::TYPE_MIXED;
            }
        }

        $locationText = $this->extractLocationText($haystack, $matchedPatterns);
        $radius = $meta['radius_km'] ?? (int) config('ai_search.default_radius_km', 25);

        // Toilets: no provider category (ADR 0032). Clarify only while AI-6 flag is off.
        $clarification = false;
        $clarificationReason = null;
        $uniqueProviders = array_values(array_unique($providerKeys));
        $uniqueFacilities = array_values(array_unique($facilityKeys));
        if (in_array('public_toilet', $uniqueFacilities, true) && !TravellerFacilitiesFeature::enabled()) {
            $clarification = true;
            $clarificationReason = 'Public toilet search is not available here yet. Try Find a service for nearby help, or ask again later.';
        }

        return new Intent(
            intentType: (string) $intentType,
            providerCategoryKeys: $uniqueProviders,
            stayTypeKeys: array_values(array_unique($stayKeys)),
            facilityTypeKeys: $uniqueFacilities,
            locationText: $locationText,
            useCurrentLocation: $meta['use_current_location'],
            radiusKm: $radius,
            urgency: $meta['urgency'],
            adapterKeys: array_values(array_unique($adapterKeys)),
            confidence: $confidence,
            clarificationRequired: $clarification,
            clarificationReason: $clarificationReason,
            source: 'rules',
        );
    }

    private function containsPhrase(string $haystack, string $needle): bool
    {
        if ($needle === '') {
            return false;
        }
        // Word-aware: allow needle as substring with word boundaries where practical.
        $quoted = preg_quote($needle, '/');
        return preg_match('/(^|[^a-z0-9])' . $quoted . '([^a-z0-9]|$)/u', $haystack) === 1;
    }

    /**
     * @param list<string> $patterns
     */
    private function extractLocationText(string $remainder, array $patterns): ?string
    {
        // Prefer explicit "near/in/around <place>" when present.
        if (preg_match('/\b(?:near|in|around|at)\s+([a-z0-9][a-z0-9\s\'-]{1,80}?)(?:\s*,\s*(?:nsw|vic|qld|sa|wa|tas|nt|act))?\s*$/ui', $remainder, $m) === 1) {
            $place = trim((string) preg_replace('/\s+/u', ' ', $m[1]));
            $place = trim($place, " \t\n\r\0\x0B,.-");
            if ($place !== '' && mb_strlen($place) >= 2) {
                return mb_convert_case($place, MB_CASE_TITLE, 'UTF-8');
            }
        }

        $text = $remainder;
        // Longer patterns first.
        usort($patterns, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));
        foreach ($patterns as $pattern) {
            $text = (string) preg_replace('/\b' . preg_quote(mb_strtolower($pattern), '/') . '\b/u', ' ', $text);
        }
        $text = (string) preg_replace(
            '/\b(near|in|around|at|for|the|a|an|me|please|find|someone who can|repair|repairer|and|or|with|within|km|of)\b/u',
            ' ',
            $text
        );
        $text = (string) preg_replace('/\b(nsw|vic|qld|sa|wa|tas|nt|act)\b/ui', ' ', $text);
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        $text = trim($text, " \t\n\r\0\x0B,.-");
        if ($text === '' || mb_strlen($text) < 2) {
            return null;
        }
        // Title-case lightly for Town::searchActive.
        return mb_convert_case($text, MB_CASE_TITLE, 'UTF-8');
    }
}
