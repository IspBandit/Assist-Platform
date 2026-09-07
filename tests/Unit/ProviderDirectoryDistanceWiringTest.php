<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProviderDirectoryDistanceWiringTest extends TestCase
{
    public function testDirectoryUsesTypedLocationBeforeDeviceCoordinates(): void
    {
        $source = $this->source('app/Controllers/Site/ProviderController.php');

        $typed = strpos($source, "if (\$location !== '')");
        $gps = strpos($source, '} elseif ($hasCoords) {');
        self::assertIsInt($typed);
        self::assertIsInt($gps);
        self::assertLessThan($gps, $typed);
        self::assertStringContainsString('$hasCoords = false;', $source);
    }

    public function testDirectoryHydratesAndRanksMeasurableProviderLocations(): void
    {
        $controller = $this->source('app/Controllers/Site/ProviderController.php');
        $view = $this->source('app/Views/public/providers-index.php');

        self::assertStringContainsString('DISTANCE_RANK_CANDIDATE_LIMIT', $controller);
        self::assertStringContainsString('hydrateDirectoryDistances', $controller);
        self::assertStringContainsString('Geo::haversineExactKm', $controller);
        self::assertStringContainsString("['providers' => \$result['rows']]", $controller);
        self::assertStringContainsString('compareDirectoryDistance', $controller);
        self::assertStringContainsString('Closest measurable provider locations are shown first', $view);
        self::assertStringContainsString("(\$p['distance_basis'] ?? '') === 'town_centre'", $view);
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }
}
