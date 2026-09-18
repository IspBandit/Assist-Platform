#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Standalone VanAssist Ask Function Test
 * Tests ProductBrandAsk logic without requiring framework initialization
 */

// Simplified version of ProductBrandAsk for testing
class ProductBrandAskSimulator
{
    private const CATEGORY_PATTERNS = [
        'towsmart' => [
            'public-weighing' => ['weighbridge', 'weigh bridge', 'mobile weighing', 'weigh my'],
            'towbars-hitches' => ['towbar', 'tow bar', 'hitch', 'weight distribution'],
            'brakes-controllers' => ['brake', 'brake controller', 'breakaway'],
            'suspension-payload' => ['suspension', 'airbag', 'load support', 'payload', 'spring'],
            'towing-training' => ['training', 'towing lesson', 'learn to tow', 'reversing'],
            'towing-inspections' => ['inspection', 'compliance check', 'safety check'],
            'tyres-wheels' => ['tyre', 'tire', 'wheel'],
        ],
        'trailerwise' => [
            'mobile-trailer-services' => ['mobile', 'on site', 'onsite', 'roadside'],
            'trailer-repairs' => ['repair', 'service', 'fault', 'broken', 'maintenance'],
            'roadworthy-inspections' => ['roadworthy', 'inspection', 'certificate', 'certifier', 'compliance'],
            'tyres-wheels-bearings' => ['tyre', 'tire', 'wheel', 'bearing', 'bearings', 'hub'],
            'brakes-axles-suspension' => ['brake', 'axle', 'suspension', 'spring'],
            'auto-electrical' => ['electrical', 'wiring', 'light', 'plug', 'battery'],
            'fabrication-engineering' => ['fabrication', 'welding', 'chassis', 'engineering', 'modification'],
            'parts-accessories' => ['part', 'parts', 'accessory', 'accessories', 'component', 'components', 'spare'],
            'manufacturers-dealers' => ['manufacturer', 'builder', 'dealer', 'new trailer'],
        ],
    ];

    public function resolve(string $brandId, string $query, ?string $deviceLocation = null): array
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        $deviceLocation = $deviceLocation !== null ? trim($deviceLocation) : null;
        if ($deviceLocation === '') {
            $deviceLocation = null;
        }

        if ($this->requestsDeviceLocation($normalised) && $deviceLocation === null) {
            return [
                'kind' => 'location',
                'category' => null,
                'location' => null,
                'heading' => 'Add your current location to continue',
                'explanation' => 'This request uses "near me"',
                'url' => '/ask?q=' . urlencode($query),
                'source' => $brandId . ' device-location safeguard',
            ];
        }

        $location = $this->location($normalised, $deviceLocation);
        $intentText = (string) preg_replace('/\b(?:near|in|around|at)\s+.+$/u', '', $normalised);

        if ($brandId === 'towsmart'
            && preg_match('/\b(what is|what does|meaning of|define)\b/u', $intentText) === 1
            && preg_match('/\b(gvm|gcm|atm|gtm|towball|tow ball|payload|tare)\b/u', $intentText) === 1) {
            return [
                'kind' => 'guidance',
                'category' => null,
                'location' => null,
                'heading' => 'Read the towing definitions and calculation guide',
                'explanation' => 'TowSmart matched this to reviewed explanatory content',
                'url' => '/tow-guide',
                'source' => 'TowSmart deterministic education matrix',
            ];
        }

        if ($brandId === 'trailerwise'
            && preg_match('/\b(registration rules|maintenance schedule|ownership guide|pre-trip checklist|pre trip checklist)\b/u', $intentText) === 1) {
            return [
                'kind' => 'guidance',
                'category' => null,
                'location' => null,
                'heading' => 'Open trailer ownership and compliance guidance',
                'explanation' => 'TrailerWise matched this to its current rules',
                'url' => '/rules',
                'source' => 'TrailerWise deterministic ownership-content matrix',
            ];
        }

        if ($brandId === 'towsmart' && preg_match('/\b(gvm|gcm|atm|gtm|towball|tow ball|payload|weight|mass|overweight|can i tow|safe to tow|capacity)\b/u', $intentText) === 1) {
            return [
                'kind' => 'calculator',
                'category' => null,
                'location' => null,
                'heading' => 'Check the exact towing combination',
                'explanation' => 'The result is guidance, not certification',
                'url' => '/calculator',
                'source' => 'TowSmart deterministic calculation-and-safety matrix',
            ];
        }

        foreach (self::CATEGORY_PATTERNS[$brandId] ?? [] as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->contains($intentText, $pattern)) {
                    $params = ['category' => $category];
                    if ($location !== null) {
                        $params['location'] = $location;
                    }
                    return [
                        'kind' => 'providers',
                        'category' => $category,
                        'location' => $location,
                        'heading' => 'Search the matching specialist category',
                        'explanation' => 'This request matched a curated category',
                        'url' => '/providers?' . http_build_query($params),
                        'source' => $brandId . ' deterministic provider-category matrix',
                    ];
                }
            }
        }

        $params = [];
        if ($location !== null) {
            $params['location'] = $location;
        }
        return [
            'kind' => 'clarify',
            'category' => null,
            'location' => $location,
            'heading' => 'Choose a service category',
            'explanation' => 'The request did not match a specific category, so no unrelated business has been substituted',
            'url' => '/providers' . ($params !== [] ? '?' . http_build_query($params) : ''),
            'source' => $brandId . ' deterministic zero-result safeguard',
        ];
    }

    private function contains(string $text, string $pattern): bool
    {
        return preg_match('/(^|[^a-z0-9])' . preg_quote($pattern, '/') . '([^a-z0-9]|$)/u', $text) === 1;
    }

    private function requestsDeviceLocation(string $query): bool
    {
        return preg_match('/\b(?:near|in|around|at)\s+(?:me|my location|current location|here)\b/u', $query) === 1;
    }

    private function location(string $query, ?string $deviceLocation): ?string
    {
        $matches = [];
        if (preg_match('/\b(?:near|in|around|at)\s+(.+?)\s*\??$/u', $query, $matches) !== 1) {
            return $deviceLocation;
        }
        $location = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B,.-");
        if ($location === '') {
            return $deviceLocation;
        }
        if (in_array($location, ['me', 'my location', 'current location', 'here'], true)) {
            return $deviceLocation;
        }
        return mb_convert_case($location, MB_CASE_TITLE, 'UTF-8');
    }
}

// Test runner
class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function test(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo "✅ PASS: $name\n";
        } catch (Exception $e) {
            $this->failed++;
            $this->failures[] = "$name: {$e->getMessage()}";
            echo "❌ FAIL: $name\n   {$e->getMessage()}\n";
        }
    }

    public function assertEquals($expected, $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $exp = var_export($expected, true);
            $act = var_export($actual, true);
            throw new Exception($message ?: "Expected $exp but got $act");
        }
    }

    public function assertStringContains(string $needle, string $haystack, string $message = ''): void
    {
        if (strpos($haystack, $needle) === false) {
            throw new Exception($message ?: "Expected string to contain '$needle' but it didn't");
        }
    }

    public function summary(): void
    {
        echo "\n" . str_repeat('=', 80) . "\n";
        echo "Test Results: {$this->passed} passed, {$this->failed} failed\n";
        if ($this->failed > 0) {
            echo "\nFailures:\n";
            foreach ($this->failures as $failure) {
                echo "  - $failure\n";
            }
        }
        echo str_repeat('=', 80) . "\n";
    }
}

// Run tests
echo "\n";
echo "╔════════════════════════════════════════════════════════════════════════════╗\n";
echo "║            VanAssist Ask Function Relevance Test Suite                    ║\n";
echo "╚════════════════════════════════════════════════════════════════════════════╝\n\n";

$service = new ProductBrandAskSimulator();
$test = new TestRunner();

echo "TowSmart Tests\n";
echo str_repeat('-', 80) . "\n";

// Calculator Intent Tests
$test->test('TowSmart: Weight capacity question routes to calculator', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'Can I safely tow 3000kg with my Ranger?');
    $test->assertEquals('calculator', $result['kind']);
    $test->assertStringContains('calculator', $result['url']);
});

$test->test('TowSmart: GCM question routes to calculator', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'What is the GCM for my vehicle?');
    $test->assertEquals('calculator', $result['kind']);
});

$test->test('TowSmart: Overweight check routes to calculator', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'Am I overweight with this caravan?');
    $test->assertEquals('calculator', $result['kind']);
});

// Guidance Intent Tests
$test->test('TowSmart: ATM definition routes to guidance', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'What does ATM mean?');
    $test->assertEquals('guidance', $result['kind']);
    $test->assertStringContains('/tow-guide', $result['url']);
});

$test->test('TowSmart: Towball definition routes to guidance', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'Define towball weight');
    $test->assertEquals('guidance', $result['kind']);
});

// Provider Category Tests
$test->test('TowSmart: Mobile weighing near Toowoomba', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'mobile weighing near Toowoomba');
    $test->assertEquals('providers', $result['kind']);
    $test->assertEquals('public-weighing', $result['category']);
    $test->assertEquals('Toowoomba', $result['location']);
});

$test->test('TowSmart: Weighbridge in Brisbane', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'weighbridge in Brisbane');
    $test->assertEquals('public-weighing', $result['category']);
    $test->assertEquals('Brisbane', $result['location']);
});

$test->test('TowSmart: Towbar installer near Ipswich', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'towbar installer near Ipswich');
    $test->assertEquals('towbars-hitches', $result['category']);
    $test->assertEquals('Ipswich', $result['location']);
});

$test->test('TowSmart: Brake controller near Cairns', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'brake controller near Cairns');
    $test->assertEquals('brakes-controllers', $result['category']);
    $test->assertEquals('Cairns', $result['location']);
});

$test->test('TowSmart: Suspension upgrade near Mackay', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'suspension upgrade near Mackay');
    $test->assertEquals('suspension-payload', $result['category']);
    $test->assertEquals('Mackay', $result['location']);
});

$test->test('TowSmart: Towing training near Melbourne', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'towing training near Melbourne');
    $test->assertEquals('towing-training', $result['category']);
    $test->assertEquals('Melbourne', $result['location']);
});

$test->test('TowSmart: Tyre replacement near Darwin', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'tyre replacement near Darwin');
    $test->assertEquals('tyres-wheels', $result['category']);
    $test->assertEquals('Darwin', $result['location']);
});

// Near Me Tests
$test->test('TowSmart: Near me WITH device location', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'mobile weighing near me', 'Brisbane City, QLD');
    $test->assertEquals('providers', $result['kind']);
    $test->assertEquals('public-weighing', $result['category']);
    $test->assertEquals('Brisbane City, QLD', $result['location']);
});

$test->test('TowSmart: Near me WITHOUT device location', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'brake controller near me');
    $test->assertEquals('location', $result['kind']);
    $test->assertEquals(null, $result['location']);
});

// Clarify Tests
$test->test('TowSmart: Unknown intent shows clarify', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'something unusual near Bundaberg');
    $test->assertEquals('clarify', $result['kind']);
    $test->assertEquals(null, $result['category']);
    $test->assertStringContains('no unrelated business', $result['explanation']);
});

echo "\n";
echo "TrailerWise Tests\n";
echo str_repeat('-', 80) . "\n";

// Guidance Intent Tests
$test->test('TrailerWise: Registration rules routes to guidance', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer registration rules');
    $test->assertEquals('guidance', $result['kind']);
    $test->assertStringContains('/rules', $result['url']);
});

$test->test('TrailerWise: Maintenance schedule routes to guidance', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'maintenance schedule for trailers');
    $test->assertEquals('guidance', $result['kind']);
});

$test->test('TrailerWise: Pre-trip checklist routes to guidance', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'pre-trip checklist');
    $test->assertEquals('guidance', $result['kind']);
});

// Provider Category Tests
$test->test('TrailerWise: Mobile service near Bendigo', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'mobile trailer service near Bendigo');
    $test->assertEquals('providers', $result['kind']);
    $test->assertEquals('mobile-trailer-services', $result['category']);
    $test->assertEquals('Bendigo', $result['location']);
});

$test->test('TrailerWise: Repair near Cairns', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'repair my trailer near Cairns');
    $test->assertEquals('trailer-repairs', $result['category']);
    $test->assertEquals('Cairns', $result['location']);
});

$test->test('TrailerWise: Roadworthy certifier near Hobart', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'roadworthy certifier near Hobart');
    $test->assertEquals('roadworthy-inspections', $result['category']);
    $test->assertEquals('Hobart', $result['location']);
});

$test->test('TrailerWise: Trailer bearings near Gladstone', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer bearings near Gladstone');
    $test->assertEquals('tyres-wheels-bearings', $result['category']);
    $test->assertEquals('Gladstone', $result['location']);
});

$test->test('TrailerWise: Trailer brakes near Warwick', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer brakes near Warwick');
    $test->assertEquals('brakes-axles-suspension', $result['category']);
    $test->assertEquals('Warwick', $result['location']);
});

$test->test('TrailerWise: Electrical wiring near Caboolture', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer wiring near Caboolture');
    $test->assertEquals('auto-electrical', $result['category']);
    $test->assertEquals('Caboolture', $result['location']);
});

$test->test('TrailerWise: Welding near Gympie', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'welding near Gympie');
    $test->assertEquals('fabrication-engineering', $result['category']);
    $test->assertEquals('Gympie', $result['location']);
});

$test->test('TrailerWise: Parts near Dubbo', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'find trailer parts near Dubbo');
    $test->assertEquals('parts-accessories', $result['category']);
    $test->assertEquals('Dubbo', $result['location']);
});

$test->test('TrailerWise: Manufacturer near Brisbane', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer manufacturer near Brisbane');
    $test->assertEquals('manufacturers-dealers', $result['category']);
    $test->assertEquals('Brisbane', $result['location']);
});

// Near Me Tests
$test->test('TrailerWise: Near me WITH device location', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer bearings near current location', 'Gladstone, QLD');
    $test->assertEquals('providers', $result['kind']);
    $test->assertEquals('tyres-wheels-bearings', $result['category']);
    $test->assertEquals('Gladstone, QLD', $result['location']);
});

$test->test('TrailerWise: Near me WITHOUT device location', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'trailer repair near me');
    $test->assertEquals('location', $result['kind']);
});

// Clarify Tests
$test->test('TrailerWise: Unknown intent shows clarify', function() use ($service, $test) {
    $result = $service->resolve('trailerwise', 'something unusual near Orange');
    $test->assertEquals('clarify', $result['kind']);
    $test->assertStringContains('no unrelated business', $result['explanation']);
});

// Edge Cases
echo "\n";
echo "Edge Case Tests\n";
echo str_repeat('-', 80) . "\n";

$test->test('Case insensitivity works', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'MOBILE WEIGHING NEAR BRISBANE');
    $test->assertEquals('public-weighing', $result['category']);
    $test->assertEquals('Brisbane', $result['location']);
});

$test->test('Multiple whitespace is normalized', function() use ($service, $test) {
    $result = $service->resolve('towsmart', 'mobile    weighing    near    Brisbane');
    $test->assertEquals('public-weighing', $result['category']);
});

echo "\n";
$test->summary();
