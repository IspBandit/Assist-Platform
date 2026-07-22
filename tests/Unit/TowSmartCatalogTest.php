<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\TowSmartCatalog;
use PHPUnit\Framework\TestCase;

final class TowSmartCatalogTest extends TestCase
{
    public function testRecoveredCatalogueCountsAreStable(): void
    {
        self::assertSame(['vehicles' => 151, 'trailers' => 3769], TowSmartCatalog::counts());
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
}
