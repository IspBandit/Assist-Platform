<?php

declare(strict_types=1);

namespace Tests\Manual;

use App\Services\ProductBrandAsk;
use PHPUnit\Framework\TestCase;

/**
 * Manual test suite for VanAssist Ask function with multiple relevant questions.
 * Run with: vendor/bin/phpunit tests/Manual/AskFunctionRelevanceTest.php --testdox
 */
final class AskFunctionRelevanceTest extends TestCase
{
    private ProductBrandAsk $service;

    protected function setUp(): void
    {
        $this->service = new ProductBrandAsk();
    }

    // ========================================================================
    // TowSmart Calculator Intent Tests
    // ========================================================================

    public function testTowSmartCalculatorIntentForWeightQuery(): void
    {
        $result = $this->service->resolve('towsmart', 'Can I safely tow 3000kg with my Ranger?');
        
        self::assertSame('calculator', $result['kind']);
        self::assertNull($result['category']);
        self::assertStringContainsString('calculator', $result['url']);
        self::assertStringContainsString('not certification', $result['explanation']);
    }

    public function testTowSmartCalculatorIntentForGcmQuery(): void
    {
        $result = $this->service->resolve('towsmart', 'What is the GCM for my vehicle?');
        
        self::assertSame('calculator', $result['kind']);
        self::assertStringContainsString('calculation', $result['explanation']);
    }

    public function testTowSmartCalculatorIntentForOverweightQuery(): void
    {
        $result = $this->service->resolve('towsmart', 'Am I overweight with this caravan?');
        
        self::assertSame('calculator', $result['kind']);
        self::assertStringContainsString('calculator', $result['url']);
    }

    public function testTowSmartCalculatorIntentForCapacityCheck(): void
    {
        $result = $this->service->resolve('towsmart', 'Check towing capacity');
        
        self::assertSame('calculator', $result['kind']);
    }

    // ========================================================================
    // TowSmart Guidance Intent Tests
    // ========================================================================

    public function testTowSmartGuidanceIntentForAtmDefinition(): void
    {
        $result = $this->service->resolve('towsmart', 'What does ATM mean?');
        
        self::assertSame('guidance', $result['kind']);
        self::assertStringContainsString('/tow-guide', $result['url']);
        self::assertStringContainsString('explanatory content', $result['explanation']);
    }

    public function testTowSmartGuidanceIntentForTowballDefinition(): void
    {
        $result = $this->service->resolve('towsmart', 'Define towball weight');
        
        self::assertSame('guidance', $result['kind']);
        self::assertStringContainsString('/tow-guide', $result['url']);
    }

    public function testTowSmartGuidanceIntentForGvmDefinition(): void
    {
        $result = $this->service->resolve('towsmart', 'What is GVM?');
        
        self::assertSame('guidance', $result['kind']);
    }

    // ========================================================================
    // TowSmart Provider Category Tests
    // ========================================================================

    public function testTowSmartPublicWeighingCategory(): void
    {
        $tests = [
            'mobile weighing near Toowoomba' => 'Toowoomba',
            'weighbridge in Brisbane' => 'Brisbane',
            'weigh bridge near Gold Coast' => 'Gold Coast',
            'weigh my caravan near Cairns' => 'Cairns',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('public-weighing', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
            self::assertStringContainsString('category=public-weighing', $result['url'], "Failed for: $query");
        }
    }

    public function testTowSmartTowbarsHitchesCategory(): void
    {
        $tests = [
            'towbar installer near Ipswich' => 'Ipswich',
            'hitch installation around Gold Coast' => 'Gold Coast',
            'tow bar fitting near Brisbane' => 'Brisbane',
            'weight distribution hitch near Toowoomba' => 'Toowoomba',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('towbars-hitches', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTowSmartBrakesControllersCategory(): void
    {
        $tests = [
            'brake controller near Cairns' => 'Cairns',
            'breakaway system installation near Townsville' => 'Townsville',
            'brake installation near Mackay' => 'Mackay',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('brakes-controllers', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTowSmartSuspensionPayloadCategory(): void
    {
        $tests = [
            'suspension upgrade near Mackay' => 'Mackay',
            'airbag suspension near Rockhampton' => 'Rockhampton',
            'load support near Bundaberg' => 'Bundaberg',
            'payload upgrade near Gympie' => 'Gympie',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('suspension-payload', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTowSmartTowingTrainingCategory(): void
    {
        $tests = [
            'towing training near Melbourne' => 'Melbourne',
            'learn to tow a caravan near Sydney' => 'Sydney',
            'towing lesson near Brisbane' => 'Brisbane',
            'reversing training near Perth' => 'Perth',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('towing-training', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTowSmartTowingInspectionsCategory(): void
    {
        $tests = [
            'towing inspection near Perth' => 'Perth',
            'safety check near Adelaide' => 'Adelaide',
            'compliance check near Darwin' => 'Darwin',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('towing-inspections', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTowSmartTyresWheelsCategory(): void
    {
        $tests = [
            'tyre replacement near Darwin' => 'Darwin',
            'tire service near Alice Springs' => 'Alice Springs',
            'wheel repair near Katherine' => 'Katherine',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('tyres-wheels', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    // ========================================================================
    // TowSmart "Near Me" Tests
    // ========================================================================

    public function testTowSmartNearMeWithDeviceLocation(): void
    {
        $result = $this->service->resolve('towsmart', 'mobile weighing near me', 'Brisbane City, QLD');
        
        self::assertSame('providers', $result['kind']);
        self::assertSame('public-weighing', $result['category']);
        self::assertSame('Brisbane City, QLD', $result['location']);
        self::assertStringContainsString('location=Brisbane+City%2C+QLD', $result['url']);
    }

    public function testTowSmartNearMeWithoutDeviceLocation(): void
    {
        $result = $this->service->resolve('towsmart', 'brake controller near me');
        
        self::assertSame('location', $result['kind']);
        self::assertNull($result['location']);
        self::assertStringContainsString('current location', $result['heading']);
    }

    public function testTowSmartCurrentLocationVariant(): void
    {
        $result = $this->service->resolve('towsmart', 'towbar installer near current location', 'Gold Coast, QLD');
        
        self::assertSame('providers', $result['kind']);
        self::assertSame('towbars-hitches', $result['category']);
        self::assertSame('Gold Coast, QLD', $result['location']);
    }

    // ========================================================================
    // TowSmart Clarify Intent Tests
    // ========================================================================

    public function testTowSmartClarifyForUnknownIntent(): void
    {
        $tests = [
            'something unusual near Bundaberg',
            'help with my boat',
            'random query near Brisbane',
        ];

        foreach ($tests as $query) {
            $result = $this->service->resolve('towsmart', $query);
            
            self::assertSame('clarify', $result['kind'], "Failed for: $query");
            self::assertNull($result['category'], "Failed for: $query");
            self::assertStringContainsString('no unrelated business', $result['explanation'], "Failed for: $query");
        }
    }

    // ========================================================================
    // TrailerWise Guidance Intent Tests
    // ========================================================================

    public function testTrailerWiseGuidanceIntentForRegistration(): void
    {
        $result = $this->service->resolve('trailerwise', 'trailer registration rules');
        
        self::assertSame('guidance', $result['kind']);
        self::assertStringContainsString('/rules', $result['url']);
        self::assertStringContainsString('ownership pathway', $result['explanation']);
    }

    public function testTrailerWiseGuidanceIntentForMaintenance(): void
    {
        $result = $this->service->resolve('trailerwise', 'maintenance schedule for trailers');
        
        self::assertSame('guidance', $result['kind']);
        self::assertStringContainsString('/rules', $result['url']);
    }

    public function testTrailerWiseGuidanceIntentForOwnershipGuide(): void
    {
        $result = $this->service->resolve('trailerwise', 'trailer ownership guide');
        
        self::assertSame('guidance', $result['kind']);
    }

    public function testTrailerWiseGuidanceIntentForPreTripChecklist(): void
    {
        $result = $this->service->resolve('trailerwise', 'pre-trip checklist');
        
        self::assertSame('guidance', $result['kind']);
    }

    // ========================================================================
    // TrailerWise Provider Category Tests
    // ========================================================================

    public function testTrailerWiseMobileServicesCategory(): void
    {
        $tests = [
            'mobile trailer service near Bendigo' => 'Bendigo',
            'onsite trailer repair near Ballarat' => 'Ballarat',
            'roadside assistance near Geelong' => 'Geelong',
            'on site service near Shepparton' => 'Shepparton',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('mobile-trailer-services', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseRepairsCategory(): void
    {
        $tests = [
            'repair my trailer near Cairns' => 'Cairns',
            'trailer maintenance near Geelong' => 'Geelong',
            'broken trailer near Shepparton' => 'Shepparton',
            'service my trailer near Warrnambool' => 'Warrnambool',
            'fault repair near Horsham' => 'Horsham',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('trailer-repairs', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseRoadworthyInspectionsCategory(): void
    {
        $tests = [
            'roadworthy certifier near Hobart' => 'Hobart',
            'trailer inspection near Launceston' => 'Launceston',
            'compliance certificate near Devonport' => 'Devonport',
            'certifier near Burnie' => 'Burnie',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('roadworthy-inspections', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseTyresWheelsBearingsCategory(): void
    {
        $tests = [
            'trailer bearings near Gladstone' => 'Gladstone',
            'trailer tyres near Bundaberg' => 'Bundaberg',
            'wheel replacement near Maryborough' => 'Maryborough',
            'hub service near Rockhampton' => 'Rockhampton',
            'tire replacement near Mackay' => 'Mackay',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('tyres-wheels-bearings', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseBrakesAxlesSuspensionCategory(): void
    {
        $tests = [
            'trailer brakes near Warwick' => 'Warwick',
            'axle repair near Toowoomba' => 'Toowoomba',
            'suspension service near Ipswich' => 'Ipswich',
            'spring replacement near Dalby' => 'Dalby',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('brakes-axles-suspension', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseAutoElectricalCategory(): void
    {
        $tests = [
            'trailer wiring near Caboolture' => 'Caboolture',
            'electrical fault near Logan' => 'Logan',
            'trailer lights near Redcliffe' => 'Redcliffe',
            'plug repair near Bribie Island' => 'Bribie Island',
            'battery service near Strathpine' => 'Strathpine',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('auto-electrical', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseFabricationEngineeringCategory(): void
    {
        $tests = [
            'welding near Gympie' => 'Gympie',
            'chassis repair near Maryborough' => 'Maryborough',
            'trailer modification near Hervey Bay' => 'Hervey Bay',
            'fabrication near Bundaberg' => 'Bundaberg',
            'engineering service near Gladstone' => 'Gladstone',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('fabrication-engineering', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWisePartsAccessoriesCategory(): void
    {
        $tests = [
            'find trailer parts near Dubbo' => 'Dubbo',
            'trailer accessories near Orange' => 'Orange',
            'spare components near Bathurst' => 'Bathurst',
            'component supplier near Wagga Wagga' => 'Wagga Wagga',
            'parts store near Albury' => 'Albury',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('parts-accessories', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    public function testTrailerWiseManufacturersDealersCategory(): void
    {
        $tests = [
            'trailer manufacturer near Brisbane' => 'Brisbane',
            'new trailer dealer near Sunshine Coast' => 'Sunshine Coast',
            'trailer builder near Gympie' => 'Gympie',
            'new trailer near Gold Coast' => 'Gold Coast',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('providers', $result['kind'], "Failed for: $query");
            self::assertSame('manufacturers-dealers', $result['category'], "Failed for: $query");
            self::assertSame($expectedLocation, $result['location'], "Failed for: $query");
        }
    }

    // ========================================================================
    // TrailerWise "Near Me" Tests
    // ========================================================================

    public function testTrailerWiseNearMeWithDeviceLocation(): void
    {
        $result = $this->service->resolve('trailerwise', 'trailer bearings near current location', 'Gladstone, QLD');
        
        self::assertSame('providers', $result['kind']);
        self::assertSame('tyres-wheels-bearings', $result['category']);
        self::assertSame('Gladstone, QLD', $result['location']);
    }

    public function testTrailerWiseNearMeWithoutDeviceLocation(): void
    {
        $result = $this->service->resolve('trailerwise', 'trailer repair near me');
        
        self::assertSame('location', $result['kind']);
        self::assertNull($result['location']);
    }

    public function testTrailerWiseMobileServiceNearMe(): void
    {
        $result = $this->service->resolve('trailerwise', 'mobile service near me', 'Cairns, QLD');
        
        self::assertSame('providers', $result['kind']);
        self::assertSame('mobile-trailer-services', $result['category']);
        self::assertSame('Cairns, QLD', $result['location']);
    }

    // ========================================================================
    // TrailerWise Clarify Intent Tests
    // ========================================================================

    public function testTrailerWiseClarifyForUnknownIntent(): void
    {
        $tests = [
            'something unusual near Orange',
            'help with my boat trailer',
            'random query near Dubbo',
        ];

        foreach ($tests as $query) {
            $result = $this->service->resolve('trailerwise', $query);
            
            self::assertSame('clarify', $result['kind'], "Failed for: $query");
            self::assertNull($result['category'], "Failed for: $query");
            self::assertStringContainsString('no unrelated business', $result['explanation'], "Failed for: $query");
        }
    }

    // ========================================================================
    // Edge Case Tests
    // ========================================================================

    public function testLocationExtractionHandlesCaseSensitivity(): void
    {
        $result = $this->service->resolve('towsmart', 'MOBILE WEIGHING NEAR BRISBANE');
        
        self::assertSame('providers', $result['kind']);
        self::assertSame('public-weighing', $result['category']);
        self::assertSame('Brisbane', $result['location']);
    }

    public function testMultipleWhitespaceIsNormalized(): void
    {
        $result = $this->service->resolve('towsmart', 'mobile    weighing    near    Brisbane');
        
        self::assertSame('providers', $result['kind']);
        self::assertSame('public-weighing', $result['category']);
    }

    public function testLocationPrecedenceDeviceOverInferred(): void
    {
        $result = $this->service->resolve('trailerwise', 'trailer repair near me', 'Gladstone, QLD');
        
        self::assertSame('Gladstone, QLD', $result['location']);
    }

    public function testUrlEncodesLocationCorrectly(): void
    {
        $result = $this->service->resolve('towsmart', 'mobile weighing near Brisbane City, QLD', 'Brisbane City, QLD');
        
        self::assertStringContainsString('location=Brisbane+City%2C+QLD', $result['url']);
    }
}
