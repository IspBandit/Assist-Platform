<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Helpers\Geo;
use App\Platform\AiSearch\Aggregate\ResultAggregator;
use PHPUnit\Framework\TestCase;

final class VanAssistRadiusInvariantTest extends TestCase
{
    public function testAggregatorRejectsEveryUnmeasurableOrOutOfRadiusResult(): void
    {
        $rows = (new ResultAggregator())->aggregate(
            providers: [
                ['id'=>1,'business_name'=>'Inside','distance_km'=>24.99],
                ['id'=>2,'business_name'=>'Outside','distance_km'=>25.01],
                ['id'=>3,'business_name'=>'Unknown'],
                ['id'=>4,'business_name'=>'Rounded outside','distance_km'=>25.0,'town_lat'=>-27.47,'town_lng'=>153.287],
            ],
            stays: [],
            externals: [],
            facilities: [],
            originLat: -27.47,
            originLng: 153.03,
            radiusKm: 25,
        );

        self::assertSame([1],array_column($rows['providers'],'id'));
    }

    public function testDistanceFilterUsesUnroundedDistanceAtRadiusBoundary(): void
    {
        $originLat = 0.0;
        $originLng = 0.0;
        $targetLng = 25.4 / 111.195;
        $rows = Geo::applyDistance(
            [['id'=>1,'business_name'=>'Just outside','town_lat'=>0.0,'town_lng'=>$targetLng]],
            $originLat,
            $originLng,
            25,
        );

        self::assertSame([], $rows, '25.4 km must not round down and leak into a 25 km search.');
    }

    public function testProviderQueriesExposeWhetherDistanceUsesARealPinOrTownCentre(): void
    {
        $provider=(string)file_get_contents(dirname(__DIR__,2).'/app/Models/Provider.php');
        self::assertStringContainsString("THEN 'provider_point' ELSE 'town_centre' END AS distance_basis",$provider);
        self::assertStringContainsString('HAVING distance_km <= ?',$provider);
    }

    public function testPhoneLayoutsContainControlsAndCollapseSecondaryCopy(): void
    {
        $css=(string)file_get_contents(dirname(__DIR__,2).'/public/assets/css/app.css');
        self::assertStringContainsString('.hero-search-panel .home-search-actions{grid-template-columns:1fr}',$css);
        self::assertStringContainsString('.facility-suggestion-form .actions{display:grid;grid-template-columns:1fr;gap:.4rem}',$css);
        self::assertStringContainsString('.stay-hero .page-trust-list{display:none}',$css);
    }
}
