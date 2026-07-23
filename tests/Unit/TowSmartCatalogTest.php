<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TowSmartCatalog;
use PHPUnit\Framework\TestCase;

final class TowSmartCatalogTest extends TestCase
{
    public function testRecoveredCatalogueCountsAreStable(): void
    {
        self::assertSame(['vehicles' => 199, 'trailers' => 3769], TowSmartCatalog::counts());
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
        self::assertCount(12, $matches);

        $labels = array_column($matches, 'label');
        self::assertNotEmpty(array_filter($labels, static fn (string $label): bool => str_contains($label, 'GXL 5-seat')));
        self::assertNotEmpty(array_filter($labels, static fn (string $label): bool => str_contains($label, 'GXL 7-seat')));

        $altitudeMatch = array_values(array_filter($matches, static fn (array $match): bool => str_contains((string) $match['label'], 'Altitude')))[0];
        $altitude = TowSmartCatalog::find('vehicles', (int) $altitudeMatch['id']);
        self::assertSame(3100, $altitude['gvm']);
        self::assertSame(2510, $altitude['kerb_weight']);
        self::assertSame(590, $altitude['payload']);
        self::assertSame(3500, $altitude['towing_capacity']);

        $altitude2025Match = array_values(array_filter($matches, static fn (array $match): bool => str_contains((string) $match['label'], 'Altitude') && str_ends_with((string) $match['label'], '2025')))[0];
        $altitude2025 = TowSmartCatalog::find('vehicles', (int) $altitude2025Match['id']);
        self::assertSame(2520, $altitude2025['kerb_weight']);
        self::assertSame(580, $altitude2025['payload']);
    }

    public function testCurrentAustralianUteGapBatchIsSearchable(): void
    {
        self::assertCount(6, TowSmartCatalog::search('vehicles', 'Kia Tasman', 20));
        self::assertCount(1, TowSmartCatalog::search('vehicles', 'BYD Shark 6', 20));
        self::assertCount(2, TowSmartCatalog::search('vehicles', 'JAC T9', 20));
        self::assertCount(2, TowSmartCatalog::search('vehicles', 'LDV Terron 9', 20));
        self::assertCount(5, TowSmartCatalog::search('vehicles', 'GWM Cannon Alpha', 20));

        $xProMatch = TowSmartCatalog::search('vehicles', 'Tasman X-Pro', 5)[0];
        $xPro = TowSmartCatalog::find('vehicles', (int) $xProMatch['id']);
        self::assertSame(2002, $xPro['rear_axle_limit']);
        self::assertSame(1013, $xPro['payload']);
        self::assertArrayHasKey('source_url', $xPro);
    }

    public function testCurrentBt50FamilyContainsAllPublishedConfigurations(): void
    {
        $matches = TowSmartCatalog::search('vehicles', 'Mazda BT-50', 50);
        $current = array_values(array_filter($matches, static fn (array $match): bool => str_contains((string) $match['label'], '2026-current')));
        self::assertCount(20, $current);

        foreach ($current as $match) {
            $vehicle = TowSmartCatalog::find('vehicles', (int) $match['id']);
            self::assertNotNull($vehicle);
            self::assertSame($vehicle['gvm'] - $vehicle['kerb_weight'], $vehicle['payload']);
            self::assertSame(3500, $vehicle['towing_capacity']);
            self::assertSame(350, $vehicle['towball_download_max']);
            self::assertArrayHasKey('source_url', $vehicle);
        }

        $thunderMatch = TowSmartCatalog::search('vehicles', 'BT-50 Thunder', 5)[0];
        $thunder = TowSmartCatalog::find('vehicles', (int) $thunderMatch['id']);
        self::assertSame(2213, $thunder['kerb_weight']);
        self::assertSame(887, $thunder['payload']);
        self::assertSame(6000, $thunder['gcm']);
    }
}
