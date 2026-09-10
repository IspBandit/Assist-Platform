<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CaravanPark;
use PHPUnit\Framework\TestCase;

final class StayPageLocationTest extends TestCase
{
    public function testSameNameStaysInDifferentStatesHaveDistinctLocationsWithoutTownData(): void
    {
        self::assertSame('Tasmania', CaravanPark::publicLocation(['name' => 'Mill Creek Campground', 'state_name' => 'Tasmania']));
        self::assertSame('New South Wales', CaravanPark::publicLocation(['name' => 'Mill Creek Campground', 'state_name' => 'New South Wales']));
    }

    public function testLocationUsesOnlyAvailableFactsAndRemovesRepeatedParts(): void
    {
        self::assertSame('', CaravanPark::publicLocation([]));
        self::assertSame('', CaravanPark::publicLocation(['town_name' => null, 'region_name' => ' ', 'state_name' => null]));
        self::assertSame('Camp Mountain, South East Queensland, Queensland', CaravanPark::publicLocation(['town_name' => 'Camp Mountain', 'region_name' => 'South East Queensland', 'state_name' => 'Queensland']));
        self::assertSame('Tasmania', CaravanPark::publicLocation(['region_name' => ' Tasmania ', 'state_name' => 'Tasmania']));
    }
}
