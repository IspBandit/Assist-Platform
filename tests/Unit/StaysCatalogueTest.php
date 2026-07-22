<?php
declare(strict_types=1);
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;

final class StaysCatalogueTest extends TestCase
{
    public function testNationwideSeedHasProvenanceAndNoFalseAuthorityClaims(): void
    {
        $path = dirname(__DIR__, 2) . '/database/seeds/stays_osm.json';
        self::assertFileExists($path);
        $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('OpenStreetMap contributors (ODbL)', $data['source']);
        self::assertGreaterThan(8000, $data['count']);
        $states = [];
        foreach ($data['stays'] as $stay) {
            self::assertSame('community', $stay['verification_type']);
            self::assertSame('openstreetmap', $stay['source_type']);
            self::assertStringStartsWith('https://www.openstreetmap.org/', $stay['source_url']);
            $states[$stay['state']] = true;
        }
        self::assertSame(['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'], array_keys(array_replace(array_fill_keys(['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'], false), $states)));
        foreach ($states as $present) { self::assertTrue($present); }
    }
}
