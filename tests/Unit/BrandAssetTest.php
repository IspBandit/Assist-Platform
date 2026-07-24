<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BrandAssetTest extends TestCase
{
    /** @return array<string,array{string}> */
    public static function brands(): array
    {
        return [
            'VanAssist' => ['vanassist'],
            'TowSmart' => ['towsmart'],
            'TrailerWise' => ['trailerwise'],
            'LocalTorque' => ['localtorque'],
        ];
    }

    #[DataProvider('brands')]
    public function testBrandMarkUsesSharedAccessibleSvgContract(string $brand): void
    {
        $svg = file_get_contents(dirname(__DIR__, 2) . '/public/assets/brands/' . $brand . '/mark.svg');

        self::assertIsString($svg);
        self::assertStringContainsString('viewBox="0 0 64 64"', $svg);
        self::assertStringContainsString('role="img"', $svg);
        self::assertStringContainsString('<title ', $svg);
        self::assertStringContainsString('stroke-width="3"', $svg);
        self::assertStringNotContainsString('linearGradient', $svg);
        self::assertStringNotContainsString('<rect', $svg);
    }
}
