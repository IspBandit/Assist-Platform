<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Search\StructuredSearchDestination;
use PHPUnit\Framework\TestCase;

final class StructuredSearchDestinationTest extends TestCase
{
    public function testDumpPointSelectionUsesFacilitySearchWithTypedLocation(): void
    {
        $path = StructuredSearchDestination::path('dump-points', 'Gladstone QLD', null, null, true);

        self::assertSame('ask?q=Dump+points+near+Gladstone+QLD', $path);
    }

    public function testFacilitySelectionPreservesDeviceCoordinates(): void
    {
        $path = StructuredSearchDestination::path('potable-water-refill', '', -23.84, 151.25, true);

        self::assertSame('ask?q=Potable+water+refill+near+me&lat=-23.84&lng=151.25', $path);
    }

    public function testFacilitySelectionStaysOnProviderSearchWhenAskIsDisabled(): void
    {
        self::assertNull(StructuredSearchDestination::path('dump-points', 'Roma', null, null, false));
    }

    public function testStayCategoriesUseStayDirectoryWithoutAsk(): void
    {
        self::assertSame(
            'stays?location=Susan+River+QLD',
            StructuredSearchDestination::path('caravan-parks-and-campgrounds', 'Susan River QLD', null, null, false)
        );
        self::assertSame('stays', StructuredSearchDestination::path('free-and-low-cost-camps', '', null, null, false));
    }

    public function testRepairCategoryIsNotRedirected(): void
    {
        self::assertNull(StructuredSearchDestination::path('toilets', 'Batehaven NSW', null, null, true));
        self::assertNull(StructuredSearchDestination::path('mobile-mechanics', 'Karratha WA', null, null, true));
    }
}
