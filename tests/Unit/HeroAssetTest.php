<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class HeroAssetTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function heroFamilies(): array
    {
        return [
            'VanAssist home' => ['vanassist-hero'],
            'TowSmart home' => ['towsmart-hero'],
            'TrailerWise home' => ['trailerwise-hero'],
            'LocalTorque home' => ['localtorque-hero'],
            'My Garage' => ['garage-hero'],
            'Rules library' => ['rules-hero'],
        ];
    }

    #[DataProvider('heroFamilies')]
    public function testHeroHasArtDirectedAvifAndWebpWithinPerformanceBudget(string $family): void
    {
        $root = dirname(__DIR__, 2) . '/public/assets/img/';
        $variants = [
            'desktop.avif' => [[1824, 864], 110_000],
            'desktop.webp' => [[1824, 864], 180_000],
            'mobile.avif' => [[720, 960], 65_000],
            'mobile.webp' => [[720, 960], 90_000],
        ];

        foreach ($variants as $suffix => [$expectedDimensions, $maxBytes]) {
            $path = $root . $family . '-' . $suffix;
            self::assertFileExists($path);
            self::assertLessThanOrEqual($maxBytes, filesize($path), $path . ' exceeds its transfer budget');
            $dimensions = getimagesize($path);
            self::assertIsArray($dimensions);
            self::assertSame($expectedDimensions, [$dimensions[0], $dimensions[1]]);
        }

        self::assertLessThan(
            filesize($root . $family . '-desktop.webp'),
            filesize($root . $family . '-desktop.avif')
        );
    }
}
