<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Intent\IntentNormaliser;
use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Intent\IntentSchemaValidator;
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

    public function testToiletMapsToFacilityWithoutInventingProviders(): void
    {
        $intent = $this->engine->interpret('Public toilets near me');
        self::assertSame(Intent::TYPE_FACILITY, $intent->intentType);
        self::assertContains('public_toilet', $intent->facilityTypeKeys);
        self::assertSame([], $intent->providerCategoryKeys);
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
