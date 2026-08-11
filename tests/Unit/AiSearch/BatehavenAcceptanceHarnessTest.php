<?php

declare(strict_types=1);

namespace Tests\Unit\AiSearch;

use App\Platform\AiSearch\Adapters\FacilitySearchPort;
use App\Platform\AiSearch\Dto\Intent;
use App\Platform\AiSearch\Dto\SearchRequest;
use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\SearchOrchestrator;
use App\Platform\AiSearch\Support\TravellerFacilitiesFeature;
use App\Services\FeatureFlag;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * S2 deterministic Ask acceptance (paid AI off) using injected facility port.
 * Does not require MariaDB — validates Batehaven mandatory query path.
 */
final class BatehavenAcceptanceHarnessTest extends TestCase
{
    protected function tearDown(): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $cache = $ref->getProperty('cache');
        $cache->setAccessible(true);
        $cache->setValue(null, null);
        parent::tearDown();
    }

    public function testDeterministicAskReturnsToiletAndDumpWithoutPaidAi(): void
    {
        $this->setFlags([
            'assist_ai_search' => true,
            TravellerFacilitiesFeature::FLAG => true,
        ]);

        $facilities = new class implements FacilitySearchPort {
            public function search(Intent $intent, ?array $town = null, ?float $lat = null, ?float $lng = null): array
            {
                if (!in_array('public_toilet', $intent->facilityTypeKeys, true)
                    || !in_array('dump_point', $intent->facilityTypeKeys, true)) {
                    return [];
                }
                return [
                    ResultProvenance::annotate([
                        'id' => 101,
                        'name' => 'Demo Batehaven Public Toilet',
                        'facility_type' => 'public_toilet',
                        'business_name' => 'Demo Batehaven Public Toilet',
                        'latitude' => -35.7325,
                        'longitude' => 150.1985,
                        'distance_km' => 0.2,
                    ], ResultProvenance::ORIGIN_CANONICAL, 'traveller_facilities'),
                    ResultProvenance::annotate([
                        'id' => 102,
                        'name' => 'Demo Batemans Bay Dump Point',
                        'facility_type' => 'dump_point',
                        'business_name' => 'Demo Batemans Bay Dump Point',
                        'latitude' => -35.7089,
                        'longitude' => 150.1782,
                        'distance_km' => 3.1,
                    ], ResultProvenance::ORIGIN_CANONICAL, 'traveller_facilities'),
                ];
            }
        };

        $orch = new SearchOrchestrator(
            facilities: $facilities,
            locationResolver: static fn (SearchRequest $_request, Intent $_intent): array => [[
                'id' => 1,
                'name' => 'Batehaven',
                'state_abbr' => 'NSW',
                'latitude' => -35.7325,
                'longitude' => 150.1985,
            ], -35.7325, 150.1985, 'test'],
        );
        $response = $orch->handle(new SearchRequest(
            rawQuery: 'public toilets and dump points near Batehaven, NSW',
            brandKey: 'vanassist',
            brandDatabaseId: 1,
            latitude: -35.7325,
            longitude: 150.1985,
            radiusKm: 50,
            requestId: 'test-batehaven-001',
            channel: 'acceptance',
            sessionId: null,
        ));

        self::assertNotSame('ai', $response->intent->source);
        self::assertContains('public_toilet', $response->intent->facilityTypeKeys);
        self::assertContains('dump_point', $response->intent->facilityTypeKeys);
        self::assertCount(2, $response->facilities);
        $types = array_map(static fn (array $f): string => (string) ($f['facility_type'] ?? ''), $response->facilities);
        self::assertContains('public_toilet', $types);
        self::assertContains('dump_point', $types);
        foreach ($response->stays as $stay) {
            self::assertNotSame('public_toilet', $stay['facility_type'] ?? null);
            self::assertNotSame('dump_point', $stay['facility_type'] ?? null);
        }
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
