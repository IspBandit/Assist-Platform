#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * VanAssist Ask Function Test Script
 * Tests the ProductBrandAsk service with multiple relevant questions
 * to verify intent matching, category routing, and location handling.
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Services\ProductBrandAsk;

function printHeader(string $text): void
{
    echo "\n" . str_repeat('=', 80) . "\n";
    echo "  $text\n";
    echo str_repeat('=', 80) . "\n\n";
}

function printResult(string $query, array $result, ?string $deviceLocation = null): void
{
    echo "Query: \"$query\"\n";
    if ($deviceLocation !== null) {
        echo "Device Location: $deviceLocation\n";
    }
    echo str_repeat('-', 80) . "\n";
    echo "Kind: {$result['kind']}\n";
    echo "Category: " . ($result['category'] ?? 'null') . "\n";
    echo "Location: " . ($result['location'] ?? 'null') . "\n";
    echo "Heading: {$result['heading']}\n";
    echo "Explanation: {$result['explanation']}\n";
    echo "URL: {$result['url']}\n";
    echo "Source: {$result['source']}\n";
    echo "\n";
}

function testBrand(string $brandId, string $brandName, array $testCases): void
{
    printHeader("Testing $brandName");
    
    $service = new ProductBrandAsk();
    $totalTests = count($testCases);
    $passedTests = 0;
    
    foreach ($testCases as $test) {
        $query = $test['query'];
        $deviceLocation = $test['location'] ?? null;
        $expectedKind = $test['expected_kind'] ?? null;
        $expectedCategory = $test['expected_category'] ?? null;
        
        $result = $service->resolve($brandId, $query, $deviceLocation);
        printResult($query, $result, $deviceLocation);
        
        // Validate expectations
        $passed = true;
        if ($expectedKind !== null && $result['kind'] !== $expectedKind) {
            echo "❌ FAILED: Expected kind '$expectedKind', got '{$result['kind']}'\n";
            $passed = false;
        }
        if ($expectedCategory !== null && $result['category'] !== $expectedCategory) {
            echo "❌ FAILED: Expected category '$expectedCategory', got '{$result['category']}'\n";
            $passed = false;
        }
        
        if ($passed) {
            echo "✅ PASSED\n";
            $passedTests++;
        }
        
        echo "\n";
    }
    
    echo str_repeat('=', 80) . "\n";
    echo "Results: $passedTests / $totalTests tests passed\n";
    echo str_repeat('=', 80) . "\n\n";
}

// ============================================================================
// TowSmart Test Cases
// ============================================================================

$towSmartTests = [
    // Calculator Intent
    [
        'query' => 'Can I safely tow 3000kg with my Ranger?',
        'expected_kind' => 'calculator',
    ],
    [
        'query' => 'What is the GCM for my vehicle?',
        'expected_kind' => 'calculator',
    ],
    [
        'query' => 'Am I overweight with this caravan?',
        'expected_kind' => 'calculator',
    ],
    [
        'query' => 'Check towing capacity',
        'expected_kind' => 'calculator',
    ],
    
    // Guidance Intent
    [
        'query' => 'What does ATM mean?',
        'expected_kind' => 'guidance',
    ],
    [
        'query' => 'Define towball weight',
        'expected_kind' => 'guidance',
    ],
    [
        'query' => 'What is GVM?',
        'expected_kind' => 'guidance',
    ],
    
    // Provider Categories with Locations
    [
        'query' => 'mobile weighing near Toowoomba',
        'expected_kind' => 'providers',
        'expected_category' => 'public-weighing',
    ],
    [
        'query' => 'weighbridge in Brisbane',
        'expected_kind' => 'providers',
        'expected_category' => 'public-weighing',
    ],
    [
        'query' => 'towbar installer near Ipswich',
        'expected_kind' => 'providers',
        'expected_category' => 'towbars-hitches',
    ],
    [
        'query' => 'hitch installation around Gold Coast',
        'expected_kind' => 'providers',
        'expected_category' => 'towbars-hitches',
    ],
    [
        'query' => 'brake controller near Cairns',
        'expected_kind' => 'providers',
        'expected_category' => 'brakes-controllers',
    ],
    [
        'query' => 'breakaway system installation near Townsville',
        'expected_kind' => 'providers',
        'expected_category' => 'brakes-controllers',
    ],
    [
        'query' => 'suspension upgrade near Mackay',
        'expected_kind' => 'providers',
        'expected_category' => 'suspension-payload',
    ],
    [
        'query' => 'airbag suspension near Rockhampton',
        'expected_kind' => 'providers',
        'expected_category' => 'suspension-payload',
    ],
    [
        'query' => 'towing training near Melbourne',
        'expected_kind' => 'providers',
        'expected_category' => 'towing-training',
    ],
    [
        'query' => 'learn to tow a caravan near Sydney',
        'expected_kind' => 'providers',
        'expected_category' => 'towing-training',
    ],
    [
        'query' => 'towing inspection near Perth',
        'expected_kind' => 'providers',
        'expected_category' => 'towing-inspections',
    ],
    [
        'query' => 'safety check near Adelaide',
        'expected_kind' => 'providers',
        'expected_category' => 'towing-inspections',
    ],
    [
        'query' => 'tyre replacement near Darwin',
        'expected_kind' => 'providers',
        'expected_category' => 'tyres-wheels',
    ],
    
    // Near Me with Device Location
    [
        'query' => 'mobile weighing near me',
        'location' => 'Brisbane City, QLD',
        'expected_kind' => 'providers',
        'expected_category' => 'public-weighing',
    ],
    [
        'query' => 'towbar installer near current location',
        'location' => 'Gold Coast, QLD',
        'expected_kind' => 'providers',
        'expected_category' => 'towbars-hitches',
    ],
    
    // Near Me without Device Location (should fail gracefully)
    [
        'query' => 'brake controller near me',
        'expected_kind' => 'location',
    ],
    
    // Clarify Intent (no match)
    [
        'query' => 'something unusual near Bundaberg',
        'expected_kind' => 'clarify',
    ],
    [
        'query' => 'help with my boat',
        'expected_kind' => 'clarify',
    ],
];

// ============================================================================
// TrailerWise Test Cases
// ============================================================================

$trailerWiseTests = [
    // Guidance Intent
    [
        'query' => 'trailer registration rules',
        'expected_kind' => 'guidance',
    ],
    [
        'query' => 'maintenance schedule for trailers',
        'expected_kind' => 'guidance',
    ],
    [
        'query' => 'trailer ownership guide',
        'expected_kind' => 'guidance',
    ],
    [
        'query' => 'pre-trip checklist',
        'expected_kind' => 'guidance',
    ],
    
    // Provider Categories with Locations
    [
        'query' => 'mobile trailer service near Bendigo',
        'expected_kind' => 'providers',
        'expected_category' => 'mobile-trailer-services',
    ],
    [
        'query' => 'onsite trailer repair near Ballarat',
        'expected_kind' => 'providers',
        'expected_category' => 'mobile-trailer-services',
    ],
    [
        'query' => 'repair my trailer near Cairns',
        'expected_kind' => 'providers',
        'expected_category' => 'trailer-repairs',
    ],
    [
        'query' => 'trailer maintenance near Geelong',
        'expected_kind' => 'providers',
        'expected_category' => 'trailer-repairs',
    ],
    [
        'query' => 'broken trailer near Shepparton',
        'expected_kind' => 'providers',
        'expected_category' => 'trailer-repairs',
    ],
    [
        'query' => 'roadworthy certifier near Hobart',
        'expected_kind' => 'providers',
        'expected_category' => 'roadworthy-inspections',
    ],
    [
        'query' => 'trailer inspection near Launceston',
        'expected_kind' => 'providers',
        'expected_category' => 'roadworthy-inspections',
    ],
    [
        'query' => 'compliance certificate near Devonport',
        'expected_kind' => 'providers',
        'expected_category' => 'roadworthy-inspections',
    ],
    [
        'query' => 'trailer bearings near Gladstone',
        'expected_kind' => 'providers',
        'expected_category' => 'tyres-wheels-bearings',
    ],
    [
        'query' => 'trailer tyres near Bundaberg',
        'expected_kind' => 'providers',
        'expected_category' => 'tyres-wheels-bearings',
    ],
    [
        'query' => 'wheel replacement near Maryborough',
        'expected_kind' => 'providers',
        'expected_category' => 'tyres-wheels-bearings',
    ],
    [
        'query' => 'trailer brakes near Warwick',
        'expected_kind' => 'providers',
        'expected_category' => 'brakes-axles-suspension',
    ],
    [
        'query' => 'axle repair near Toowoomba',
        'expected_kind' => 'providers',
        'expected_category' => 'brakes-axles-suspension',
    ],
    [
        'query' => 'suspension service near Ipswich',
        'expected_kind' => 'providers',
        'expected_category' => 'brakes-axles-suspension',
    ],
    [
        'query' => 'trailer wiring near Caboolture',
        'expected_kind' => 'providers',
        'expected_category' => 'auto-electrical',
    ],
    [
        'query' => 'electrical fault near Logan',
        'expected_kind' => 'providers',
        'expected_category' => 'auto-electrical',
    ],
    [
        'query' => 'trailer lights near Redcliffe',
        'expected_kind' => 'providers',
        'expected_category' => 'auto-electrical',
    ],
    [
        'query' => 'welding near Gympie',
        'expected_kind' => 'providers',
        'expected_category' => 'fabrication-engineering',
    ],
    [
        'query' => 'chassis repair near Maryborough',
        'expected_kind' => 'providers',
        'expected_category' => 'fabrication-engineering',
    ],
    [
        'query' => 'trailer modification near Hervey Bay',
        'expected_kind' => 'providers',
        'expected_category' => 'fabrication-engineering',
    ],
    [
        'query' => 'find trailer parts near Dubbo',
        'expected_kind' => 'providers',
        'expected_category' => 'parts-accessories',
    ],
    [
        'query' => 'trailer accessories near Orange',
        'expected_kind' => 'providers',
        'expected_category' => 'parts-accessories',
    ],
    [
        'query' => 'spare components near Bathurst',
        'expected_kind' => 'providers',
        'expected_category' => 'parts-accessories',
    ],
    [
        'query' => 'trailer manufacturer near Brisbane',
        'expected_kind' => 'providers',
        'expected_category' => 'manufacturers-dealers',
    ],
    [
        'query' => 'new trailer dealer near Sunshine Coast',
        'expected_kind' => 'providers',
        'expected_category' => 'manufacturers-dealers',
    ],
    [
        'query' => 'trailer builder near Gympie',
        'expected_kind' => 'providers',
        'expected_category' => 'manufacturers-dealers',
    ],
    
    // Near Me with Device Location
    [
        'query' => 'trailer bearings near current location',
        'location' => 'Gladstone, QLD',
        'expected_kind' => 'providers',
        'expected_category' => 'tyres-wheels-bearings',
    ],
    [
        'query' => 'mobile service near me',
        'location' => 'Cairns, QLD',
        'expected_kind' => 'providers',
        'expected_category' => 'mobile-trailer-services',
    ],
    
    // Near Me without Device Location
    [
        'query' => 'trailer repair near me',
        'expected_kind' => 'location',
    ],
    
    // Clarify Intent
    [
        'query' => 'something unusual near Orange',
        'expected_kind' => 'clarify',
    ],
    [
        'query' => 'help with my boat trailer',
        'expected_kind' => 'clarify',
    ],
];

// ============================================================================
// Run Tests
// ============================================================================

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                   VanAssist Ask Function Test Suite                       ║\n";
echo "║                                                                            ║\n";
echo "║  Testing ProductBrandAsk service with multiple relevant questions         ║\n";
echo "║  to validate intent matching, category routing, and location handling.    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";

testBrand('towsmart', 'TowSmart', $towSmartTests);
testBrand('trailerwise', 'TrailerWise', $trailerWiseTests);

echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                          Test Suite Complete                               ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n";
echo "\n";
