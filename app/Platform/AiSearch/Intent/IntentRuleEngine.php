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
        ['id'=>'R20','patterns'=>['12 volt','12v','low voltage','lights not working'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['12-volt-electrical'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R21','patterns'=>['240 volt','240v','mains power','power point'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['240-volt-electrical'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R22','patterns'=>['solar','battery','batteries','dc dc','dc-dc','inverter'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['solar-and-batteries','12-volt-electrical'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R23','patterns'=>['fridge','refrigerator','freezer','not cooling'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['refrigeration','appliance-repairs'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R24','patterns'=>['air conditioner','air conditioning','aircon','a/c'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['air-conditioning'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R25','patterns'=>['gas appliance','gas stove','gas oven','gas heater'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['gas-appliance-servicing'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R26','patterns'=>['water leak','water pump','plumbing','leaking pipe','water tank'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['plumbing-and-water-leaks'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R27','patterns'=>['hot water','water heater'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['hot-water-systems'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R28','patterns'=>['cassette toilet','caravan toilet','toilet repair'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['toilets'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R29','patterns'=>['suspension','leaf spring','broken spring','axle','grinding underneath','noise underneath'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['suspension','trailer-and-engineering','general-caravan-repairs'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.85],
        ['id'=>'R30','patterns'=>['chassis','structural damage','frame damage'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['structural-repairs','trailer-and-engineering'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R31','patterns'=>['fibreglass','fiberglass','body panel'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['fibreglass-repairs','structural-repairs'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R32','patterns'=>['awning','annexe'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['awning-repairs'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R33','patterns'=>['roof leak','leaking roof','water ingress'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['roof-leaks'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R34','patterns'=>['starlink','satellite','uhf','communications'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['starlink-and-communications'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R35','patterns'=>['insurance repair','insurance repairs','insurance claim'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['insurance-repairs'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R36','patterns'=>['pre trip inspection','pre-trip inspection','before my trip'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['pre-trip-inspection','general-servicing'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.85],
        ['id'=>'R37','patterns'=>['general service','caravan service','routine service'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['general-servicing'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R38','patterns'=>['roadworthy','safety certificate'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['roadworthy-inspection'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R39','patterns'=>['groceries','supermarket','travel supplies'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['groceries-and-travel-supplies'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.85],
        ['id'=>'R40','patterns'=>['emergency accommodation','motel tonight','hotel tonight'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['emergency-accommodation'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.85],
        ['id'=>'R41','patterns'=>['vet','veterinary','pet friendly'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['pet-friendly-travel-and-veterinary'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.85],
        ['id'=>'R42','patterns'=>['4wd recovery','4x4 recovery','bogged','remote recovery'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['4wd-and-remote-area-recovery'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R43','patterns'=>['locksmith','locked out','lost keys','broken lock'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['locksmith-and-security'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R44','patterns'=>['windscreen','windshield','auto glass','broken window'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['windscreen-and-auto-glass'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R45','patterns'=>['caravan parts','rv parts','spare parts'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['caravan-and-rv-parts','vehicle-parts-and-accessories'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.85],
        ['id'=>'R46','patterns'=>['towbar','tow bar','hitch','weight distribution hitch','towing equipment'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['towing-equipment-and-accessories'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R47','patterns'=>['caravan wash','vehicle wash','car wash'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['vehicle-and-caravan-washing'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.88],
        ['id'=>'R48','patterns'=>['caravan storage','store my caravan','rv storage'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['caravan-storage'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
        ['id'=>'R49','patterns'=>['mobile welder','welding','fabrication'],'intent_type'=>Intent::TYPE_PROVIDER,'provider_category_keys'=>['mobile-welding-and-fabrication'],'facility_type_keys'=>[],'stay_type_keys'=>[],'adapter_keys'=>['providers'],'confidence'=>0.9],
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

        if ($hits === [] && preg_match('/\b(caravan|motorhome|rv|camper|trailer|tow vehicle)\b/u', $haystack) === 1
            && preg_match('/\b(broken|break|failed|failure|fault|problem|issue|noise|grinding|leak|damaged|not work|not working|help|repair|fix)\b/u', $haystack) === 1) {
            $hits['RFALLBACK'] = [
                'id' => 'RFALLBACK', 'patterns' => [], 'intent_type' => Intent::TYPE_PROVIDER,
                'provider_category_keys' => ['general-caravan-repairs', 'unsure-which-service-is-needed'],
                'facility_type_keys' => [], 'stay_type_keys' => [], 'adapter_keys' => ['providers'],
                'confidence' => 0.62,
            ];
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
        // Prefer the final explicit "near/in/around/at <place>". Using the
        // final marker avoids treating "in my caravan near Emerald" as a town.
        $markers = [];
        if (preg_match_all('/\b(?:near|in|around|at)\s+/ui', $remainder, $markers, PREG_OFFSET_CAPTURE) > 0) {
            $last = $markers[0][count($markers[0]) - 1];
            $place = mb_substr($remainder, (int) $last[1] + mb_strlen((string) $last[0]));
            // Stop conversational wording after the place from becoming part
            // of the town (for example: "near Gympie on my caravan").
            $place = (string) preg_replace(
                '/\s+(?:on|for|with|because|where|who|that|which)\s+(?:my|our|the|a|an|i|we)\b.*$/ui',
                '',
                $place
            );
            $place = (string) preg_replace('/\s*,?\s*(?:nsw|vic|qld|sa|wa|tas|nt|act)\s*$/ui', '', $place);
            $place = trim((string) preg_replace('/\s+/u', ' ', $place));
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
