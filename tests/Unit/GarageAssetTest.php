<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\GarageAsset;
use PHPUnit\Framework\TestCase;

final class GarageAssetTest extends TestCase
{
    public function testEverySupportedAssetHasAReadableLabel(): void
    {
        self::assertCount(12, GarageAsset::TYPES);
        foreach (GarageAsset::TYPES as $type => $label) {
            self::assertNotSame('', $type);
            self::assertNotSame('', $label);
            self::assertSame($label, GarageAsset::typeLabel($type));
        }
    }

    public function testAssetTypesMapToTheRegulatoryFilterWithoutLeakingPrivateData(): void
    {
        self::assertSame('motorcycle', GarageAsset::rulesVehicle('motorcycle'));
        self::assertSame('heavy-vehicle', GarageAsset::rulesVehicle('heavy_vehicle'));
        self::assertSame('trailer', GarageAsset::rulesVehicle('caravan'));
        self::assertSame('trailer', GarageAsset::rulesVehicle('horse_float'));
        self::assertSame('street-rod', GarageAsset::rulesVehicle('street_rod'));
    }

    public function testAustralianRegistrationJurisdictionsAreExplicit(): void
    {
        self::assertSame(['ACT', 'NSW', 'NT', 'QLD', 'SA', 'TAS', 'VIC', 'WA'], array_keys(GarageAsset::JURISDICTIONS));
    }
}
