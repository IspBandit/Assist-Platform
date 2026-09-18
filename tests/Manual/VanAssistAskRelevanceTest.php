<?php

declare(strict_types=1);

namespace Tests\Manual;

use App\Platform\AiSearch\Intent\IntentRuleEngine;
use App\Platform\AiSearch\Dto\Intent;
use PHPUnit\Framework\TestCase;

/**
 * VanAssist Ask Function Relevance Tests
 * 
 * Tests the IntentRuleEngine (deterministic rules) with multiple relevant real-world
 * queries to validate that VanAssist produces accurate intent classification and routing.
 * 
 * Run with: vendor/bin/phpunit tests/Manual/VanAssistAskRelevanceTest.php --testdox
 */
final class VanAssistAskRelevanceTest extends TestCase
{
    private IntentRuleEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new IntentRuleEngine();
    }

    // ========================================================================
    // Provider Intent Tests - Common Roadside Issues
    // ========================================================================

    public function testRoadsideEmergencyQueries(): void
    {
        $tests = [
            'broken down near Cairns' => 'towing-and-vehicle-recovery',
            'need a tow truck in Brisbane' => 'towing-and-vehicle-recovery',
            'my van broke down near Toowoomba' => 'towing-and-vehicle-recovery',
            'vehicle recovery near Gold Coast' => 'towing-and-vehicle-recovery',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
            self::assertGreaterThan(0.5, $intent->confidence, "Low confidence for: $query");
        }
    }

    public function testTyreAndWheelIssues(): void
    {
        $tests = [
            'flat tyre near Bundaberg' => 'tyres-and-wheels',
            'need new tyres in Mackay' => 'tyres-and-wheels',
            'wheel balance near Rockhampton' => 'tyres-and-wheels',
            'tire pressure check near Gladstone' => 'tyres-and-wheels',
            'spare wheel replacement near Gympie' => 'tyres-and-wheels',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    public function testBrakeIssues(): void
    {
        $tests = [
            'caravan brakes not working near Emerald' => 'brakes-and-bearings',
            'brake service near Roma' => 'brakes-and-bearings',
            'wheel bearing noise near Longreach' => 'brakes-and-bearings',
            'bearing replacement in Barcaldine' => 'brakes-and-bearings',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    // ========================================================================
    // Provider Intent Tests - Electrical Issues
    // ========================================================================

    public function testElectricalIssues(): void
    {
        $tests = [
            'auto electrician near Brisbane' => 'auto-electrical-and-batteries',
            'lights not working near Cairns' => 'auto-electrical-and-batteries',
            '12v power issue near Townsville' => '12-volt-electrical',
            '240 volt problem near Mackay' => '240-volt-electrical',
            'battery not charging near Gladstone' => 'auto-electrical-and-batteries',
            'solar panels not working near Roma' => 'solar-and-batteries',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    // ========================================================================
    // Provider Intent Tests - Appliances
    // ========================================================================

    public function testApplianceIssues(): void
    {
        $tests = [
            'fridge not cooling near Emerald' => 'refrigeration',
            'air conditioning broken near Darwin' => 'air-conditioning',
            'gas stove not working near Alice Springs' => 'gas-appliance-servicing',
            'hot water system near Katherine' => 'hot-water-systems',
            'toilet needs repair near Tennant Creek' => 'toilets',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    // ========================================================================
    // Provider Intent Tests - Plumbing and Water
    // ========================================================================

    public function testPlumbingAndWaterIssues(): void
    {
        $tests = [
            'water leak near Bundaberg' => 'plumbing-and-water-leaks',
            'water pump not working near Maryborough' => 'plumbing-and-water-leaks',
            'plumber near Hervey Bay' => 'plumbing-and-water-leaks',
            'hot water system near Gladstone' => 'hot-water-systems',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    // ========================================================================
    // Provider Intent Tests - Structural and Body
    // ========================================================================

    public function testStructuralAndBodyIssues(): void
    {
        $tests = [
            'chassis damage near Longreach' => 'structural-repairs',
            'fibreglass repair near Bundaberg' => 'fibreglass-repairs',
            'body panel damage near Rockhampton' => 'fibreglass-repairs',
            'suspension broken near Emerald' => 'suspension',
            'leaf spring replacement near Roma' => 'suspension',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    // ========================================================================
    // Provider Intent Tests - General Repairs
    // ========================================================================

    public function testGeneralRepairs(): void
    {
        $tests = [
            'caravan repairer near Cairns' => 'general-caravan-repairs',
            'mobile caravan service near Townsville' => 'general-caravan-repairs',
            'caravan maintenance near Brisbane' => 'general-caravan-repairs',
            'RV repair near Gold Coast' => 'general-caravan-repairs',
        ];

        foreach ($tests as $query => $expectedCategory) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
            self::assertContains($expectedCategory, $intent->providerCategoryKeys, "Failed for: $query");
        }
    }

    // ========================================================================
    // Facility Intent Tests - Traveller Facilities
    // ========================================================================

    public function testTravellerFacilityQueries(): void
    {
        $tests = [
            'dump point near Batemans Bay' => 'dump-points',
            'waste disposal near Narooma' => 'dump-points',
            'drinking water near Emerald' => 'potable-water-refill',
            'water refill near Roma' => 'potable-water-refill',
            'LPG refill near Cairns' => 'lpg-refills-and-bottle-exchange',
            'gas bottle exchange near Townsville' => 'lpg-refills-and-bottle-exchange',
        ];

        foreach ($tests as $query => $expectedKey) {
            $intent = $this->engine->interpret($query);
            
            if ($expectedKey === 'lpg-refills-and-bottle-exchange') {
                // LPG is a provider category
                self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType, "Failed for: $query");
                self::assertContains($expectedKey, $intent->providerCategoryKeys, "Failed for: $query");
            } else {
                // Dump points and water are facilities
                self::assertSame(Intent::TYPE_FACILITY, $intent->intentType, "Failed for: $query");
                self::assertContains($expectedKey, $intent->facilityTypeKeys, "Failed for: $query");
            }
            self::assertGreaterThan(0.5, $intent->confidence, "Low confidence for: $query");
        }
    }

    // ========================================================================
    // Stay Intent Tests - Accommodation
    // ========================================================================

    public function testCaravanParkQueries(): void
    {
        $tests = [
            'caravan park near Cairns',
            'need somewhere to stay near Brisbane',
            'RV park near Gold Coast',
            'holiday park near Sunshine Coast',
            'powered site near Bundaberg',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_STAY, $intent->intentType, "Failed for: $query");
            self::assertContains('caravan_park', $intent->stayTypeKeys, "Failed for: $query");
            self::assertContains('stays', $intent->adapterKeys, "Failed for: $query");
        }
    }

    public function testFreeCampingQueries(): void
    {
        $tests = [
            'free camp near Emerald',
            'free camping near Roma',
            'somewhere to stay free near Longreach',
            'unpowered camping near Barcaldine',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_STAY, $intent->intentType, "Failed for: $query");
            self::assertContains('free_camp', $intent->stayTypeKeys, "Failed for: $query");
        }
    }

    public function testRestAreaQueries(): void
    {
        $tests = [
            'rest area near Darwin',
            'overnight stop near Katherine',
            'rest stop near Alice Springs',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_STAY, $intent->intentType, "Failed for: $query");
            self::assertTrue(
                in_array('rest_area', $intent->stayTypeKeys, true) 
                || in_array('free_camp', $intent->stayTypeKeys, true),
                "Failed for: $query - expected rest_area or free_camp"
            );
        }
    }

    // ========================================================================
    // Location Extraction Tests
    // ========================================================================

    public function testLocationExtraction(): void
    {
        $tests = [
            'dump point near Batemans Bay' => 'Batemans Bay',
            'tyres in Brisbane' => 'Brisbane',
            'caravan park around Gold Coast' => 'Gold Coast',
            'water refill at Emerald' => 'Emerald',
        ];

        foreach ($tests as $query => $expectedLocation) {
            $intent = $this->engine->interpret($query);
            
            self::assertNotNull($intent->locationText, "No location extracted for: $query");
            self::assertStringContainsStringIgnoringCase($expectedLocation, $intent->locationText, "Failed for: $query");
        }
    }

    public function testNearMeDetection(): void
    {
        $tests = [
            'tyres near me',
            'dump point near current location',
            'caravan park near here',
            'electrician at my location',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertTrue($intent->useCurrentLocation, "Failed to detect 'near me' for: $query");
        }
    }

    // ========================================================================
    // Radius Extraction Tests
    // ========================================================================

    public function testRadiusExtraction(): void
    {
        $tests = [
            'tyres within 50 km' => 50,
            'electrician within 100km' => 100,
            'caravan park within 25 kilometres' => 25,
        ];

        foreach ($tests as $query => $expectedRadius) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame($expectedRadius, $intent->radiusKm, "Failed for: $query");
        }
    }

    public function testCombinedLocationAndRadius(): void
    {
        $intent = $this->engine->interpret('tyres near Cairns within 50 km');
        
        self::assertSame(Intent::TYPE_PROVIDER, $intent->intentType);
        self::assertStringContainsStringIgnoringCase('Cairns', $intent->locationText ?? '');
        self::assertSame(50, $intent->radiusKm);
        self::assertFalse($intent->useCurrentLocation);
    }

    public function testNearMeWithRadius(): void
    {
        $intent = $this->engine->interpret('electrician near me within 100 km');
        
        self::assertTrue($intent->useCurrentLocation);
        self::assertSame(100, $intent->radiusKm);
    }

    // ========================================================================
    // Urgency Detection Tests
    // ========================================================================

    public function testUrgencyDetection(): void
    {
        $urgentTests = [
            'urgent tyre repair near Cairns',
            'emergency electrician near Brisbane',
            'need towing urgently near Gold Coast',
        ];

        foreach ($urgentTests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame('urgent', $intent->urgency, "Failed to detect urgency for: $query");
        }
    }

    // ========================================================================
    // Unknown Intent / Clarification Tests
    // ========================================================================

    public function testAmbiguousQueriesRequireClarification(): void
    {
        $tests = [
            'help',
            'need assistance',
            'something wrong',
            'broken',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertSame(Intent::TYPE_UNKNOWN, $intent->intentType, "Should be unknown for: $query");
            self::assertTrue($intent->clarificationRequired, "Should require clarification for: $query");
        }
    }

    public function testCompletelyUnrelatedQueries(): void
    {
        $tests = [
            'what is the weather tomorrow',
            'pizza near me',
            'best restaurant in Sydney',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            // Should be unknown or have low confidence
            $isAppropriate = $intent->intentType === Intent::TYPE_UNKNOWN 
                || $intent->confidence < 0.5 
                || $intent->clarificationRequired;
            
            self::assertTrue($isAppropriate, "Should not confidently match for: $query");
        }
    }

    // ========================================================================
    // Confidence Level Tests
    // ========================================================================

    public function testHighConfidenceForClearQueries(): void
    {
        $tests = [
            'dump point near Batemans Bay',
            'caravan park near Cairns',
            'tyres near Brisbane',
            'auto electrician near Gold Coast',
        ];

        foreach ($tests as $query) {
            $intent = $this->engine->interpret($query);
            
            self::assertGreaterThan(0.7, $intent->confidence, "Should have high confidence for: $query");
            self::assertFalse($intent->clarificationRequired, "Should not need clarification for: $query");
        }
    }

    // ========================================================================
    // Real-World Journey Tests
    // ========================================================================

    public function testRealWorldTravellerJourneys(): void
    {
        // Simulate real traveller scenarios
        $scenarios = [
            'Roadside breakdown' => [
                'query' => 'my caravan broke down near Emerald need help',
                'expected_type' => Intent::TYPE_PROVIDER,
                'expected_categories' => ['general-caravan-repairs', 'towing-and-vehicle-recovery'],
            ],
            'Pre-trip preparation' => [
                'query' => 'need a brake check before leaving Cairns',
                'expected_type' => Intent::TYPE_PROVIDER,
                'expected_categories' => ['brakes-and-bearings'],
            ],
            'Finding facilities' => [
                'query' => 'where can I dump waste near Batemans Bay',
                'expected_type' => Intent::TYPE_FACILITY,
                'expected_categories' => ['dump-points'],
            ],
            'Overnight accommodation' => [
                'query' => 'need somewhere to stay tonight near Brisbane',
                'expected_type' => Intent::TYPE_STAY,
                'expected_stay_types' => ['caravan_park'],
            ],
        ];

        foreach ($scenarios as $scenario => $details) {
            $intent = $this->engine->interpret($details['query']);
            
            self::assertSame($details['expected_type'], $intent->intentType, "Failed scenario: $scenario");
            
            if (isset($details['expected_categories'])) {
                $matched = false;
                foreach ($details['expected_categories'] as $category) {
                    if (in_array($category, $intent->providerCategoryKeys, true)) {
                        $matched = true;
                        break;
                    }
                }
                self::assertTrue($matched, "No matching category for scenario: $scenario");
            }
            
            if (isset($details['expected_stay_types'])) {
                foreach ($details['expected_stay_types'] as $stayType) {
                    self::assertContains($stayType, $intent->stayTypeKeys, "Failed scenario: $scenario");
                }
            }
        }
    }

    // ========================================================================
    // Edge Case Tests
    // ========================================================================

    public function testCaseInsensitivity(): void
    {
        $variations = [
            'DUMP POINT NEAR CAIRNS',
            'dump point near cairns',
            'Dump Point Near Cairns',
            'dUmP pOiNt NeAr CaIrNs',
        ];

        $results = [];
        foreach ($variations as $query) {
            $intent = $this->engine->interpret($query);
            $results[] = [
                'type' => $intent->intentType,
                'facilities' => $intent->facilityTypeKeys,
                'location' => $intent->locationText,
            ];
        }

        // All variations should produce the same result
        foreach ($results as $result) {
            self::assertSame(Intent::TYPE_FACILITY, $result['type']);
            self::assertContains('dump-points', $result['facilities']);
        }
    }

    public function testWhitespaceNormalization(): void
    {
        $intent1 = $this->engine->interpret('dump    point    near    Cairns');
        $intent2 = $this->engine->interpret('dump point near Cairns');
        
        self::assertSame($intent1->intentType, $intent2->intentType);
        self::assertSame($intent1->facilityTypeKeys, $intent2->facilityTypeKeys);
    }
}
