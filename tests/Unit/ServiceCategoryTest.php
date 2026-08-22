<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\ServiceCategory;
use PHPUnit\Framework\TestCase;

final class ServiceCategoryTest extends TestCase
{
    public function testTravellerServicesAreGroupedForEasyDiscovery(): void
    {
        self::assertSame('Travel essentials & places', ServiceCategory::vanAssistGroup('fuel-and-travel-stops'));
        self::assertSame('Travel essentials & places', ServiceCategory::vanAssistGroup('dump-points'));
        self::assertSame('Breakdown, vehicle & roadside help', ServiceCategory::vanAssistGroup('towing-and-vehicle-recovery'));
        self::assertSame('Safety, inspection & compliance', ServiceCategory::vanAssistGroup('weighbridges-and-mobile-weighing'));
        self::assertSame('Caravan systems & appliances', ServiceCategory::vanAssistGroup('solar-and-batteries'));
    }

    public function testGroupingDoesNotDropAnyCategory(): void
    {
        $categories = [
            ['slug' => 'fuel-and-travel-stops', 'name' => 'Fuel and travel stops'],
            ['slug' => 'roof-leaks', 'name' => 'Roof leaks'],
            ['slug' => 'caravan-and-rv-parts', 'name' => 'Caravan and RV parts'],
            ['slug' => 'unsure-which-service-is-needed', 'name' => 'Unsure'],
        ];

        $grouped = ServiceCategory::groupedForVanAssist($categories);
        self::assertCount(count($categories), array_merge(...array_values($grouped)));
    }
}
