<?php

declare(strict_types=1);

namespace Tests\Unit\DataSources;

use PHPUnit\Framework\TestCase;

/**
 * S1 prep: demo fixtures must cover the Batehaven acceptance geography.
 */
final class BatehavenDemoFixtureTest extends TestCase
{
    public function testDemoToiletsIncludeBatehaven(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/datasets/demo-public-toilets.csv';
        self::assertFileExists($path);
        $csv = (string) file_get_contents($path);
        self::assertStringContainsString('Batehaven', $csv);
        self::assertStringContainsString('demo-toilet-3', $csv);
        self::assertStringContainsString('-35.7325', $csv);
        self::assertStringContainsString('150.1985', $csv);
    }

    public function testDemoDumpPointsIncludeBatemansBayNearBatehaven(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/datasets/demo-dump-points.geojson';
        self::assertFileExists($path);
        $json = json_decode((string) file_get_contents($path), true);
        self::assertIsArray($json);
        $found = false;
        foreach ($json['features'] ?? [] as $feature) {
            $name = (string) ($feature['properties']['name'] ?? '');
            $coords = $feature['geometry']['coordinates'] ?? null;
            if (str_contains($name, 'Batemans Bay') && is_array($coords)) {
                // Within ~5 km of Batehaven demo toilet (-35.7325, 150.1985)
                $lng = (float) $coords[0];
                $lat = (float) $coords[1];
                self::assertEqualsWithDelta(150.1782, $lng, 0.05);
                self::assertEqualsWithDelta(-35.7089, $lat, 0.05);
                $found = true;
            }
        }
        self::assertTrue($found, 'Expected a Batemans Bay dump-point feature near Batehaven');
    }

    public function testDemoDrinkingWaterIncludesBatehaven(): void
    {
        $path = dirname(__DIR__, 3) . '/resources/datasets/demo-drinking-water.csv';
        self::assertFileExists($path);
        $csv = (string) file_get_contents($path);
        self::assertStringContainsString('Batehaven', $csv);
        self::assertStringContainsString('drinking_water', $csv);
    }

    public function testDemoRestAndVisitorFixturesExist(): void
    {
        $rest = dirname(__DIR__, 3) . '/resources/datasets/demo-rest-areas.csv';
        $visitor = dirname(__DIR__, 3) . '/resources/datasets/demo-visitor-information.csv';
        self::assertFileExists($rest);
        self::assertFileExists($visitor);
        self::assertStringContainsString('rest_area', (string) file_get_contents($rest));
        self::assertStringContainsString('Batehaven', (string) file_get_contents($rest));
        self::assertStringContainsString('visitor_information', (string) file_get_contents($visitor));
        self::assertStringContainsString('Batemans Bay', (string) file_get_contents($visitor));
    }
}
