<?php

declare(strict_types=1);

namespace Tests\Unit\Search;

use App\Platform\AiSearch\Provenance\ResultProvenance;
use App\Platform\AiSearch\Support\PlacesRescueFeature;
use App\Platform\DataSources\ConnectorInterface;
use App\Services\FeatureFlag;
use App\Services\Search\ZeroResultProviderRescueService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class ZeroResultProviderRescueServiceTest extends TestCase
{
    public function testRescueIsNoOpWhenFlagOff(): void
    {
        $this->forceFlag(false);
        $connector = new class implements ConnectorInterface {
            public function key(): string
            {
                return 'google_places';
            }

            public function search(array $request, array $credentials, array $settings = []): array
            {
                self::fail('Places must not be called when the rescue flag is off.');
            }
        };

        $service = new ZeroResultProviderRescueService(null, null, $connector);
        $result = $service->rescue(
            ['refrigeration'],
            ['name' => 'Charters Towers', 'state_abbr' => 'QLD'],
            -20.077, 146.262,
            1,
            300
        );

        self::assertSame([], $result['providers']);
        self::assertSame([], $result['externals']);
        self::assertSame(0, $result['created']);
    }

    public function testExternalCardShapeUsesLiveProvenance(): void
    {
        $service = new ZeroResultProviderRescueService();
        $method = (new ReflectionClass($service))->getMethod('externalCard');
        $method->setAccessible(true);
        $card = $method->invoke($service, [
            'external_id' => 'places/ChIJtest',
            'business_name' => 'NQ Caravan Fridge Repairs',
            'formatted_address' => 'Townsville QLD',
            'phone' => '07 1234 5678',
            'website' => 'https://example.test',
            'latitude' => -19.26,
            'longitude' => 146.82,
        ], -20.077, 146.262, 300);

        self::assertIsArray($card);
        self::assertSame(ResultProvenance::ORIGIN_EXTERNAL_LIVE, $card['assist_origin']);
        self::assertSame('google_places', $card['assist_source']);
        self::assertSame('places/ChIJtest', $card['assist_source_record_id']);
        self::assertTrue((bool) ($card['assist_pending_review'] ?? false));
        self::assertSame('NQ Caravan Fridge Repairs', $card['business_name']);
    }

    public function testExternalCardDropsResultsBeyondRadius(): void
    {
        $service = new ZeroResultProviderRescueService();
        $method = (new ReflectionClass($service))->getMethod('externalCard');
        $method->setAccessible(true);
        $card = $method->invoke($service, [
            'external_id' => 'places/far',
            'business_name' => 'Far Away Fridge',
            'latitude' => -23.7,
            'longitude' => 133.87,
        ], -20.077, 146.262, 150);

        self::assertNull($card);
    }

    public function testSortByDistancePrefersLocalHits(): void
    {
        $service = new ZeroResultProviderRescueService();
        $method = (new ReflectionClass($service))->getMethod('sortByDistance');
        $method->setAccessible(true);
        $sorted = $method->invoke($service, [
            ['business_name' => 'Far', 'distance_km' => 140.0],
            ['business_name' => 'Local', 'distance_km' => 0.6],
            ['business_name' => 'Unknown'],
        ]);

        self::assertSame('Local', $sorted[0]['business_name']);
        self::assertSame('Far', $sorted[1]['business_name']);
        self::assertSame('Unknown', $sorted[2]['business_name']);
    }

    public function testRefrigerationRescueQueriesIncludeFridgeAndApplianceFallback(): void
    {
        \App\Core\Config::set('places_rescue', require base_path('config/places_rescue.php'));
        $service = new ZeroResultProviderRescueService();
        $queries = $service->queriesForSlug('refrigeration');
        self::assertNotSame([], $queries);
        $joined = mb_strtolower(implode(' ', $queries));
        self::assertStringContainsString('fridge', $joined);
        self::assertMatchesRegularExpression('/appliance|refrigerat|caravan|rv/i', $joined);
        self::assertGreaterThanOrEqual(2, count($queries));
    }

    public function testBuildMessagesSaysLocalOnlyWhenNearby(): void
    {
        \App\Core\Config::set('places_rescue', require base_path('config/places_rescue.php'));
        $service = new ZeroResultProviderRescueService();
        $local = $service->buildMessages('Charters Towers, QLD', [
            ['distance_km' => 3.2, 'business_name' => 'Local Fridge'],
        ], []);
        self::assertNotNull($local['message']);
        self::assertStringContainsString('for this area', (string) $local['message']);
        self::assertNotNull($local['attribution']);

        $far = $service->buildMessages('Charters Towers, QLD', [
            ['distance_km' => 140.0, 'business_name' => 'Distant'],
        ], []);
        self::assertNotNull($far['message']);
        self::assertStringContainsString('Nothing close in Charters Towers, QLD', (string) $far['message']);
        self::assertStringContainsString('140 km', (string) $far['message']);
        self::assertStringNotContainsString('for this area', (string) $far['message']);
    }

    public function testBuildMessagesOmitsCopyWhenNoDistances(): void
    {
        $service = new ZeroResultProviderRescueService();
        $none = $service->buildMessages('Longreach, QLD', [['business_name' => 'Unknown']], []);
        self::assertNull($none['message']);
        self::assertNull($none['attribution']);
    }

    private function forceFlag(bool $enabled): void
    {
        $ref = new ReflectionClass(FeatureFlag::class);
        $prop = $ref->getProperty('cache');
        $prop->setAccessible(true);
        $prop->setValue(null, [PlacesRescueFeature::FLAG => $enabled]);
    }
}
