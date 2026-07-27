<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\NationalImportSeeder;
use PHPUnit\Framework\TestCase;

final class NationalImportSeederTest extends TestCase
{
    public function testBatteryRetailerDoesNotInheritMechanicalServices(): void
    {
        $services = NationalImportSeeder::serviceNamesForImport(
            ['name' => 'Battery World Tempe', 'website' => 'https://www.batteryworld.com.au'],
            ['autoelec', 'mechanical']
        );

        self::assertSame(['Auto electrical and batteries'], $services);
        self::assertNotContains('Mechanical repairs', $services);
        self::assertNotContains('Tyres and wheels', $services);
        self::assertNotContains('Suspension', $services);
    }

    public function testRetailGlassTyreFuelAndGasBusinessesStayNarrow(): void
    {
        self::assertSame(['Windscreen and auto glass'], NationalImportSeeder::serviceNamesForImport(['name' => "O'Brien Windscreens"], ['mechanical']));
        self::assertSame(['Vehicle parts and accessories'], NationalImportSeeder::serviceNamesForImport(['name' => 'Supercheap Auto'], ['mechanical']));
        self::assertSame(['Tyres and wheels'], NationalImportSeeder::serviceNamesForImport(['name' => 'Bob Jane T-Marts'], ['mechanical']));
        self::assertSame(['Fuel and travel stops'], NationalImportSeeder::serviceNamesForImport(['name' => 'Metro Petroleum'], ['gasfitter']));
        self::assertSame(['LPG refills and bottle exchange'], NationalImportSeeder::serviceNamesForImport(['name' => 'Elgas'], ['gasfitter']));
    }

    public function testGenericImportsReceiveOnlyTheirDirectSourceServices(): void
    {
        self::assertSame(
            ['Fuel and travel stops'],
            NationalImportSeeder::serviceNamesForImport(['name' => 'Outback Fuel'], ['fuel'])
        );
        self::assertSame(
            ['Mechanical repairs', '12-volt electrical'],
            NationalImportSeeder::serviceNamesForImport(['name' => 'Regional Automotive'], ['mechanical', 'autoelec'])
        );
    }
}
