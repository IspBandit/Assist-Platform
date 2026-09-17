<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Cpaq2026ImportService;
use PHPUnit\Framework\TestCase;

final class Cpaq2026ImportServiceTest extends TestCase
{
    public function testTradeCategoryMappingCoversKnownLabels(): void
    {
        $mapped = Cpaq2026ImportService::mapTradeCategories([
            'Accessories',
            'Annexe & Awnings',
            'Gas',
            'Service & Repair',
            'Weight',
            'Towing',
        ]);

        self::assertContains('caravan-and-rv-parts', $mapped['slugs']);
        self::assertContains('vehicle-parts-and-accessories', $mapped['slugs']);
        self::assertContains('awning-repairs', $mapped['slugs']);
        self::assertContains('gas-appliance-servicing', $mapped['slugs']);
        self::assertContains('lpg-refills-and-bottle-exchange', $mapped['slugs']);
        self::assertContains('general-caravan-repairs', $mapped['slugs']);
        self::assertContains('general-servicing', $mapped['slugs']);
        self::assertContains('weighbridges-and-mobile-weighing', $mapped['slugs']);
        self::assertContains('towing-and-vehicle-recovery', $mapped['slugs']);
        self::assertSame([], $mapped['unmapped']);
        self::assertSame([], $mapped['schema_gap']);
    }

    public function testFinanceAndDriverInstructionAreSchemaGapsWithoutFakeServices(): void
    {
        $mapped = Cpaq2026ImportService::mapTradeCategories([
            'Finance',
            'Driver Instruction or Training',
            'Caravan Sales',
        ]);

        self::assertSame(['caravan-and-rv-parts'], $mapped['slugs']);
        self::assertContains('Finance', $mapped['schema_gap']);
        self::assertContains('Driver Instruction or Training', $mapped['schema_gap']);
        self::assertSame([], $mapped['unmapped']);
    }

    public function testUnknownTradeCategoryIsUnmapped(): void
    {
        $mapped = Cpaq2026ImportService::mapTradeCategories(['Unicorn Polishing']);
        self::assertSame([], $mapped['slugs']);
        self::assertSame(['Unicorn Polishing'], $mapped['unmapped']);
    }

    public function testHireMapsWeaklyToCaravanAndRvParts(): void
    {
        $mapped = Cpaq2026ImportService::mapTradeCategories(['Hire']);
        self::assertSame(['caravan-and-rv-parts'], $mapped['slugs']);
    }

    public function testNormaliseHelpers(): void
    {
        self::assertSame('big4cairnsholidaypark', Cpaq2026ImportService::normaliseName('BIG4 Cairns Holiday Park'));
        self::assertSame('0730001111', Cpaq2026ImportService::normalisePhone('(07) 3000-1111'));
        self::assertSame('0412345678', Cpaq2026ImportService::normalisePhone('+61 412 345 678'));
        self::assertSame('example.com.au', Cpaq2026ImportService::websiteHost('https://www.example.com.au/path'));
        self::assertSame('example.com.au', Cpaq2026ImportService::websiteHost('example.com.au'));
        self::assertNull(Cpaq2026ImportService::websiteHost(''));
    }

    public function testShouldImportParkSkipsPureResidentialWithoutTouristDetail(): void
    {
        self::assertFalse(Cpaq2026ImportService::shouldImportPark([
            'pure_residential' => true,
            'detail_matched' => false,
            'listing_kind' => 'pure_residential',
        ]));
        self::assertTrue(Cpaq2026ImportService::shouldImportPark([
            'pure_residential' => false,
            'detail_matched' => true,
            'listing_kind' => 'caravan_holiday_park',
        ]));
        self::assertTrue(Cpaq2026ImportService::shouldImportPark([
            'pure_residential' => true,
            'detail_matched' => true,
            'listing_kind' => 'caravan_holiday_park',
        ]));
    }

    public function testFacilityClaimsFromPetsAndRvAttributes(): void
    {
        $claims = Cpaq2026ImportService::facilityClaimsFromAttributes([
            'pets_allowed' => 'on_application',
            'dump_point' => true,
            'wifi' => true,
            'rv_motorhome_sites' => true,
            'accessible' => true,
        ]);
        $byType = [];
        foreach ($claims as $claim) {
            $byType[$claim['facility_type']] = $claim;
        }

        self::assertSame('conditional', $byType['pet_friendly']['facility_status']);
        self::assertSame('on_application', $byType['pet_friendly']['facility_value']);
        self::assertSame('yes', $byType['dump_point']['facility_status']);
        self::assertSame('yes', $byType['wifi']['facility_status']);
        self::assertSame('yes', $byType['motorhome_suitable']['facility_status']);
        self::assertSame('yes', $byType['big_rig_suitable']['facility_status']);
        self::assertSame('yes', $byType['accessibility']['facility_status']);

        $noPets = Cpaq2026ImportService::facilityClaimsFromAttributes(['pets_allowed' => false]);
        self::assertSame('no', $noPets[0]['facility_status']);
        self::assertSame('pet_friendly', $noPets[0]['facility_type']);
    }

    public function testParkColumnsDoNotInventPetsOnApplication(): void
    {
        $cols = Cpaq2026ImportService::parkColumnsFromAttributes([
            'pets_allowed' => 'on_application',
            'dump_point' => true,
            'powered_sites' => false,
        ]);
        self::assertNull($cols['pets_allowed']);
        self::assertSame(1, $cols['dump_point']);
        self::assertSame(0, $cols['powered_sites']);
    }

    public function testParkExternalIdTruncatesToColumnLimit(): void
    {
        $long = str_repeat('a', 105);
        self::assertSame(100, strlen(Cpaq2026ImportService::truncateParkExternalId($long)));
        self::assertSame('short-id', Cpaq2026ImportService::truncateParkExternalId('short-id'));
    }
}
