<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FacebookLaunchPackTest extends TestCase
{
    /** @return iterable<string,array{string,int,int}> */
    public static function productionAssets(): iterable
    {
        yield 'cover' => ['brand-assets/facebook/vanassist-cover-v2.png', 1640, 624];
        yield 'profile' => ['brand-assets/facebook/vanassist-profile-v2.png', 1080, 1080];
        yield 'first post' => ['brand-assets/facebook/vanassist-first-post-v2.png', 1200, 630];
    }

    #[DataProvider('productionAssets')]
    public function testVanAssistLaunchAssetsHaveExactDimensions(string $path, int $width, int $height): void
    {
        $fullPath = base_path($path);
        self::assertFileExists($fullPath);
        $size = getimagesize($fullPath);
        self::assertNotFalse($size);
        self::assertSame([$width, $height], [$size[0], $size[1]]);
    }

    public function testFastFacebookExportsStayLightweight(): void
    {
        $cover = base_path('brand-assets/facebook/vanassist-cover-fast-v2.jpg');
        $post = base_path('brand-assets/facebook/vanassist-first-post-fast-v2.jpg');
        self::assertFileExists($cover);
        self::assertFileExists($post);
        self::assertLessThanOrEqual(100_000, filesize($cover));
        self::assertLessThanOrEqual(120_000, filesize($post));
    }
}
