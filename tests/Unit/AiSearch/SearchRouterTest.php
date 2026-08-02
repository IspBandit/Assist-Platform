<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Routing\SearchRouter;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class SearchRouterTest extends TestCase
{
    private SearchRouter $router;

    protected function setUp(): void
    {
        $this->router = new SearchRouter();
    }

    protected function tearDown(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
        parent::tearDown();
    }

    public function testOnlyExecutableAdaptersAreReturned(): void
    {
        $this->setFlagCache([]);
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_MIXED,
            'provider_category_keys' => ['dump-points'],
            'stay_type_keys' => ['caravan_park'],
            'facility_type_keys' => [],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['providers', 'stays', 'traveller_facilities', 'datasets'],
            'confidence' => 0.8,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);

        self::assertSame(['providers', 'stays', 'datasets'], $this->router->adaptersFor($intent));
    }

    public function testTravellerFacilitiesExecutableWhenFlagOn(): void
    {
        $this->setFlagCache([TravellerFacilitiesFeature::FLAG => true]);
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => [],
            'stay_type_keys' => [],
            'facility_type_keys' => ['public_toilet'],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['traveller_facilities'],
            'confidence' => 0.85,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);
        self::assertSame(['traveller_facilities'], $this->router->adaptersFor($intent));
    }

    public function testDatasetAugmentWhenLocalWeak(): void
    {
        $adapters = $this->router->withDatasetAugment(['providers'], 0, true);
        self::assertSame(['providers', 'datasets'], $adapters);
        self::assertSame(['providers'], $this->router->withDatasetAugment(['providers'], 10, true));
        self::assertSame(['providers'], $this->router->withDatasetAugment(['providers'], 0, false));
    }

    public function testProviderCategoriesFallbackWhenAdapterKeysEmpty(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_PROVIDER,
            'provider_category_keys' => ['tyres-and-wheels'],
            'stay_type_keys' => [],
            'facility_type_keys' => [],
            'location_text' => 'Emerald',
            'use_current_location' => false,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => [],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);

        self::assertSame(['providers'], $this->router->adaptersFor($intent));
    }

    public function testStayTypesFallbackWhenAdapterKeysEmpty(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_STAY,
            'provider_category_keys' => [],
            'stay_type_keys' => ['caravan_park'],
            'facility_type_keys' => [],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => [],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);

        self::assertSame(['stays'], $this->router->adaptersFor($intent));
    }

    public function testFacilityOnlyIntentEmptyWhenFlagOff(): void
    {
        $this->setFlagCache([]);
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => [],
            'stay_type_keys' => [],
            'facility_type_keys' => ['public_toilet'],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['traveller_facilities'],
            'confidence' => 0.85,
            'clarification_required' => true,
            'clarification_reason' => 'Not yet available.',
        ]);

        self::assertSame([], $this->router->adaptersFor($intent));
    }

    /** @param array<string,bool> $flags */
    private function setFlagCache(array $flags): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, $flags);
    }
}
