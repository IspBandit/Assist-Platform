<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Intent\IntentNormaliser;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
use App\Platform\AiSearch\Intent\TaxonomyRegistry;
use App\Platform\AiSearch\Routing\SearchRouter;
use App\Platform\AiSearch\Dto\Intent;
use PHPUnit\Framework\TestCase;

final class IntentRuleEngineTest extends TestCase
{
    private IntentRuleEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new IntentRuleEngine();
    }

    /** @return array<string,array{0:string,1:string,2:?string}> */
    public static function goldenQueries(): array
    {
        return [
            'dump' => ['Dump point near Batehaven', Intent::TYPE_FACILITY, 'dump-points'],
            'water' => ['drinking water near Emerald', Intent::TYPE_FACILITY, 'potable-water-refill'],
            'lpg' => ['LPG refill near Batemans Bay', Intent::TYPE_PROVIDER, 'lpg-refills-and-bottle-exchange'],
            'park' => ['Caravan park nearby', Intent::TYPE_STAY, null],
            'mobile' => ['Mobile caravan repairer near Emerald', Intent::TYPE_PROVIDER, 'general-caravan-repairs'],
            'electrician' => ['Auto electrician within 50 km', Intent::TYPE_PROVIDER, 'auto-electrical-and-batteries'],
            'solar' => ['my solar panels do not work and I am in Gladstone', Intent::TYPE_PROVIDER, 'auto-electrical-and-batteries'],
            'tyres' => ['tyres near me', Intent::TYPE_PROVIDER, 'tyres-and-wheels'],
            'towing' => ['towing near Gladstone', Intent::TYPE_PROVIDER, 'towing-and-vehicle-recovery'],
            'brakes' => ['Someone who can repair caravan brakes', Intent::TYPE_PROVIDER, 'brakes-and-bearings'],
        ];
    }

    /** @dataProvider goldenQueries */
    public function testGoldenQueriesResolve(string $query, string $intentType, ?string $category): void
    {
        $intent = $this->engine->interpret($query);
        self::assertSame($intentType, $intent->intentType);
        self::assertGreaterThan(0.5, $intent->confidence);
        if ($category !== null) {
            self::assertContains($category, $intent->providerCategoryKeys);
        }
        if ($intentType === Intent::TYPE_STAY) {
            self::assertContains('caravan_park', $intent->stayTypeKeys);
            self::assertContains('stays', $intent->adapterKeys);
        }
    }

    public function testNearMeAndRadiusExtraction(): void
    {
        $meta = IntentNormaliser::analyse('tyres near me within 50 km');
        self::assertTrue($meta['use_current_location']);
        self::assertSame(50, $meta['radius_km']);

        $intent = $this->engine->interpret('Auto electrician within 50 km');
        self::assertSame(50, $intent->radiusKm);
        self::assertFalse($intent->useCurrentLocation);
    }

    public function testAmbiguousQueryAsksClarification(): void
    {
        $intent = $this->engine->interpret('help please');
        self::assertSame(Intent::TYPE_UNKNOWN, $intent->intentType);
        self::assertTrue($intent->clarificationRequired);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function serviceLanguageQueries(): array
    {
        return [
            '12v' => ['my 12v lights are not working near Roma', '12-volt-electrical'],
            '240v' => ['my 240 volt power point failed near Mackay', '240-volt-electrical'],
            'solar' => ['solar batteries not charging in Gladstone', 'solar-and-batteries'],
            'fridge' => ['caravan fridge is not cooling near Emerald', 'refrigeration'],
            'aircon' => ['aircon has stopped working in Cairns', 'air-conditioning'],
            'gas' => ['gas stove will not light near Dubbo', 'gas-appliance-servicing'],
            'plumbing' => ['water pump is leaking near Roma', 'plumbing-and-water-leaks'],
            'hot-water' => ['no hot water in my van near Mackay', 'hot-water-systems'],
            'cassette' => ['cassette toilet needs repair near Cairns', 'toilets'],
            'suspension' => ['broken leaf spring near Emerald', 'suspension'],
            'structure' => ['caravan chassis is damaged near Longreach', 'structural-repairs'],
            'fibreglass' => ['fibreglass body panel repair in Bundaberg', 'fibreglass-repairs'],
            'awning' => ['awning is torn near Rockhampton', 'awning-repairs'],
            'roof' => ['roof leak in my caravan near Townsville', 'roof-leaks'],
            'communications' => ['Starlink installation near Mackay', 'starlink-and-communications'],
            'insurance' => ['insurance repairs for caravan near Brisbane', 'insurance-repairs'],
            'inspection' => ['pre trip inspection near Gladstone', 'pre-trip-inspection'],
            'service' => ['routine caravan service near Emerald', 'general-servicing'],
            'roadworthy' => ['need a roadworthy near Cairns', 'roadworthy-inspection'],
            'groceries' => ['groceries and travel supplies near me', 'groceries-and-travel-supplies'],
            'accommodation' => ['emergency accommodation near Roma', 'emergency-accommodation'],
            'vet' => ['need a vet near Mackay', 'pet-friendly-travel-and-veterinary'],
            'remote-recovery' => ['bogged and need 4wd recovery near Birdsville', '4wd-and-remote-area-recovery'],
            'locksmith' => ['locked out of caravan near Emerald', 'locksmith-and-security'],
            'glass' => ['broken windscreen near Gladstone', 'windscreen-and-auto-glass'],
            'parts' => ['need caravan spare parts near Cairns', 'caravan-and-rv-parts'],
            'hitch' => ['weight distribution hitch near Roma', 'towing-equipment-and-accessories'],
            'wash' => ['caravan wash near Townsville', 'vehicle-and-caravan-washing'],
            'storage' => ['store my caravan near Brisbane', 'caravan-storage'],
            'welder' => ['mobile welder for caravan near Longreach', 'mobile-welding-and-fabrication'],
        ];
    }

    /** @dataProvider serviceLanguageQueries */
    public function testReasonableServiceLanguageResolves(string $query, string $category): void
    {
        $intent = $this->engine->interpret($query);
        self::assertNotSame(Intent::TYPE_UNKNOWN, $intent->intentType, $query);
        self::assertContains($category, $intent->providerCategoryKeys, $query);
        self::assertContains('providers', $intent->adapterKeys, $query);
    }

    public function testUnclearCaravanFaultGetsUsefulFallbackButGeneralChatDoesNot(): void
    {
        $fault = $this->engine->interpret('something strange is broken in my caravan near Emerald');
        self::assertSame(Intent::TYPE_PROVIDER, $fault->intentType);
        self::assertContains('general-caravan-repairs', $fault->providerCategoryKeys);
        self::assertSame('Emerald', $fault->locationText);

        $general = $this->engine->interpret('write me a poem about Queensland');
        self::assertSame(Intent::TYPE_UNKNOWN, $general->intentType);
        self::assertSame([], $general->adapterKeys);
    }

    public function testFullSeededProviderTaxonomyIsAvailableToPaidInterpreter(): void
    {
        self::assertCount(54, TaxonomyRegistry::PROVIDER_CATEGORY_KEYS);
        self::assertSame(TaxonomyRegistry::PROVIDER_CATEGORY_KEYS, array_values(array_unique(TaxonomyRegistry::PROVIDER_CATEGORY_KEYS)));
        foreach (self::serviceLanguageQueries() as [, $category]) {
            self::assertTrue(TaxonomyRegistry::isProviderCategoryKey($category), $category);
        }
    }

    public function testToiletMapsToFacilityWithoutInventingProviders(): void
    {
        $intent = $this->engine->interpret('Public toilets near me');
        self::assertSame(Intent::TYPE_FACILITY, $intent->intentType);
        self::assertContains('public_toilet', $intent->facilityTypeKeys);
        self::assertSame([], $intent->providerCategoryKeys);
        self::assertContains('traveller_facilities', $intent->adapterKeys);
        self::assertTrue($intent->clarificationRequired);
    }

    public function testBatehavenToiletsAndDumpPointsMandatoryQuery(): void
    {
        $intent = $this->engine->interpret('public toilets and dump points near Batehaven, NSW');
        self::assertContains('public_toilet', $intent->facilityTypeKeys);
        self::assertContains('dump_point', $intent->facilityTypeKeys);
        self::assertContains('traveller_facilities', $intent->adapterKeys);
        self::assertSame('Batehaven', $intent->locationText);
        self::assertNotSame(Intent::TYPE_STAY, $intent->intentType);
        self::assertNotContains('caravan_park', $intent->stayTypeKeys);
    }

    public function testSchemaValidatorStripsUnknownCategories(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['dump-points', 'not-a-real-slug'],
            'stay_type_keys' => [],
            'facility_type_keys' => [],
            'location_text' => 'Emerald',
            'use_current_location' => false,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['providers', 'datasets'],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);
        $validated = IntentSchemaValidator::validate($intent);
        self::assertTrue($validated['ok']);
        self::assertSame(['dump-points'], $validated['intent']->providerCategoryKeys);
        self::assertSame(['providers', 'datasets'], $validated['intent']->adapterKeys);
    }

    public function testSchemaValidatorStripsTravellerFacilitiesWhenFlagOff(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => ['dump-points'],
            'stay_type_keys' => [],
            'facility_type_keys' => ['dump_point'],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['traveller_facilities', 'providers'],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);
        $validated = IntentSchemaValidator::validate($intent);
        self::assertTrue($validated['ok']);
        self::assertSame(['providers'], $validated['intent']->adapterKeys);
    }

    public function testRouterOnlyReturnsExecutableAdapters(): void
    {
        $router = new SearchRouter();
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_MIXED,
            'provider_category_keys' => ['dump-points'],
            'stay_type_keys' => ['caravan_park'],
            'facility_type_keys' => [],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['providers', 'stays', 'traveller_facilities'],
            'confidence' => 0.8,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);
        $validated = IntentSchemaValidator::validate($intent);
        $adapters = $router->adaptersFor($validated['intent']);
        self::assertSame(['providers', 'stays'], $adapters);
    }
}
