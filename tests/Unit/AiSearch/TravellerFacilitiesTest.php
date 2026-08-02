<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\TravellerFacilitySearchAdapter;
use App\Platform\AiSearch\Aggregate\ResultAggregator;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class TravellerFacilitiesTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
        parent::tearDown();
    }

    public function testFeatureFlagDefaultsOff(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);
        self::assertFalse(TravellerFacilitiesFeature::enabled());
        self::assertSame('assist_ai_traveller_facilities', TravellerFacilitiesFeature::FLAG);
    }

    public function testAdapterReturnsEmptyWhenFlagOff(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, []);

        $adapter = new TravellerFacilitySearchAdapter();
        $intent = Intent::fromArray([
            'intent_type' => Intent::TYPE_FACILITY,
            'provider_category_keys' => [],
            'stay_type_keys' => [],
            'facility_type_keys' => ['dump_point'],
            'location_text' => null,
            'use_current_location' => true,
            'radius_km' => 25,
            'urgency' => 'normal',
            'adapter_keys' => ['traveller_facilities'],
            'confidence' => 0.9,
            'clarification_required' => false,
            'clarification_reason' => null,
        ]);
        self::assertSame([], $adapter->search($intent, null, -35.7, 150.2));
    }

    public function testAggregatorKeepsFacilitiesSeparateFromProviders(): void
    {
        $agg = new ResultAggregator();
        $result = $agg->aggregate(
            [['id' => 1, 'business_name' => 'Biz', 'is_inferred' => 0]],
            [],
            [],
            [[
                'id' => 9,
                'name' => 'Bay Dump Point',
                'facility_type' => 'dump_point',
                'assist_origin' => ResultProvenance::ORIGIN_CANONICAL,
                'assist_provenance_label' => 'VanAssist listing',
            ]]
        );

        self::assertCount(1, $result['providers']);
        self::assertCount(1, $result['facilities']);
        self::assertSame('Bay Dump Point', $result['facilities'][0]['name']);
        self::assertSame([], $result['externals']);
    }

    public function testMigrationDefinesFacilitiesTableNotCaravanParks(): void
    {
        $sql = (string) file_get_contents(dirname(__DIR__, 3) . '/database/migrations/108_assist_traveller_facilities.sql');
        self::assertStringContainsString('CREATE TABLE IF NOT EXISTS traveller_facilities', $sql);
        self::assertStringNotContainsString('ALTER TABLE caravan_parks', $sql);
        self::assertStringContainsString('assist_ai_traveller_facilities', $sql);
    }
}
