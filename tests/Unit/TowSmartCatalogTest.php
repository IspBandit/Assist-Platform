<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TowSmartCatalog;
use PHPUnit\Framework\TestCase;

final class TowSmartCatalogTest extends TestCase
{
    public function testRecoveredCatalogueCountsAreStable(): void
    {
        self::assertSame(['vehicles' => 157, 'trailers' => 3769], TowSmartCatalog::counts());
    }

    public function testVehicleSearchReturnsReferenceSpecification(): void
    {
        $matches = TowSmartCatalog::search('vehicles', 'Ranger', 5);
        self::assertNotEmpty($matches);
        $item = TowSmartCatalog::find('vehicles', (int) $matches[0]['id']);
        self::assertNotNull($item);
        self::assertSame('advertised_reference', $item['specification_status']);
        self::assertArrayHasKey('gvm', $item);
        self::assertArrayHasKey('towing_capacity', $item);
    }

    public function testTrailerSearchMatchesBrandAndModel(): void
    {
        $matches = TowSmartCatalog::search('trailers', 'Jayco', 10);
        self::assertNotEmpty($matches);
        self::assertStringContainsStringIgnoringCase('Jayco', (string) $matches[0]['label']);
    }

    public function testCurrentPrado250SeriesGradesAreSearchableWithDistinctPayloads(): void
    {
        $matches = TowSmartCatalog::search('vehicles', 'Prado 250 Series', 20);
        self::assertCount(6, $matches);

        $labels = array_column($matches, 'label');
        self::assertNotEmpty(array_filter($labels, static fn (string $label): bool => str_contains($label, 'GXL 5-seat')));
        self::assertNotEmpty(array_filter($labels, static fn (string $label): bool => str_contains($label, 'GXL 7-seat')));

        $altitudeMatch = array_values(array_filter($matches, static fn (array $match): bool => str_contains((string) $match['label'], 'Altitude')))[0];
        $altitude = TowSmartCatalog::find('vehicles', (int) $altitudeMatch['id']);
        self::assertSame(3100, $altitude['gvm']);
        self::assertSame(2510, $altitude['kerb_weight']);
        self::assertSame(590, $altitude['payload']);
        self::assertSame(3500, $altitude['towing_capacity']);
    }
}
