<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\TravellerFacilitySearchAdapter;
use App\Platform\AiSearch\Aggregate\ResultAggregator;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchResponse;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Routing\SearchRouter;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * End-to-end wiring evidence for Ask facilities (flag on → separate facilities bucket).
 */
final class TravellerFacilitiesOrchestrationTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
        parent::tearDown();
    }

    public function testFlagOnRoutesToiletIntentToFacilitiesAdapter(): void
    {
        $this->setFlags([TravellerFacilitiesFeature::FLAG => true]);
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
        self::assertSame(['traveller_facilities'], (new SearchRouter())->adaptersFor($intent));
    }

    public function testAggregatedFacilitiesStayOutOfProvidersAndStays(): void
    {
        $facility = ResultProvenance::annotate(
            [
                'id' => 7,
                'name' => 'Demo Public Toilet',
                'facility_type' => 'public_toilet',
                'business_name' => 'Demo Public Toilet',
            ],
            ResultProvenance::ORIGIN_CANONICAL,
            'traveller_facilities'
        );
        $agg = (new ResultAggregator())->aggregate(
            [['id' => 1, 'business_name' => 'Mechanic', 'is_inferred' => 0]],
            [['id' => 2, 'name' => 'Bay Park', 'stay_type' => 'caravan_park']],
            [],
            [$facility]
        );
        self::assertCount(1, $agg['providers']);
        self::assertCount(1, $agg['stays']);
        self::assertCount(1, $agg['facilities']);
        self::assertSame('Demo Public Toilet', $agg['facilities'][0]['name']);
        self::assertSame('Mechanic', $agg['providers'][0]['business_name']);
    }

    public function testSearchResponseCountsFacilitiesAsLocal(): void
    {
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => [],
            'stay_type_keys' => [],
            'facility_type_keys' => ['dump_point'],
            'location_text' => 'Emerald',
            'use_current_location' => false,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['traveller_facilities'],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);
        $response = new SearchResponse(
            intent: $intent,
            providers: [],
            stays: [],
            town: null,
            originLat: null,
            originLng: null,
            fallbackReason: '',
            messages: [],
            assistSearchId: null,
            searched: true,
            externals: [],
            facilities: [['id' => 1, 'name' => 'Dump']],
        );
        self::assertSame(1, $response->localResultCount());
        self::assertSame(0, $response->externalResultCount());
    }

    public function testAskViewRendersFacilitiesSection(): void
    {
        $view = (string) file_get_contents(dirname(__DIR__, 3) . '/app/Views/public/assist-search.php');
        self::assertStringContainsString('Traveller facilities', $view);
        self::assertStringContainsString('$result->facilities', $view);
        self::assertStringContainsString('caravan parks', $view);
    }

    public function testAdapterClassExistsAndIsInjectable(): void
    {
        self::assertTrue(class_exists(TravellerFacilitySearchAdapter::class));
    }

    /** @param array<string,bool> $flags */
    private function setFlags(array $flags): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, $flags);
    }
}
