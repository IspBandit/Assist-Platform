<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\SocialMediaAssetService;
use PHPUnit\Framework\TestCase;

final class SocialMediaAssetServiceTest extends TestCase
{
    public function testPlatformFormatsUseProductionDimensions(): void
    {
        $formats = SocialMediaAssetService::formats();
        self::assertSame([1080, 1920], [$formats['instagram-story']['width'], $formats['instagram-story']['height']]);
        self::assertSame([1640, 624], [$formats['facebook-cover']['width'], $formats['facebook-cover']['height']]);
        self::assertSame([1080, 1080], [$formats['instagram-profile']['width'], $formats['instagram-profile']['height']]);
    }

    public function testEveryBrandHasCopyForEveryIntention(): void
    {
        foreach (['vanassist', 'towsmart', 'trailerwise', 'localtorque'] as $brand) {
            foreach (array_keys(SocialMediaAssetService::intentions()) as $intention) {
                $copy = SocialMediaAssetService::copyFor($brand, $intention);
                self::assertNotSame('', $copy['headline']);
                self::assertStringContainsString('#', $copy['caption']);
            }
        }
    }

    public function testCopyRemainsBrandSpecific(): void
    {
        self::assertStringContainsString('TowSmart', SocialMediaAssetService::copyFor('towsmart', 'launch')['caption']);
        self::assertStringContainsString('TrailerWise', SocialMediaAssetService::copyFor('trailerwise', 'service-discovery')['caption']);
        self::assertStringContainsString('VanAssist', SocialMediaAssetService::copyFor('vanassist', 'provider-recruitment')['caption']);
        self::assertStringContainsString('LocalTorque', SocialMediaAssetService::copyFor('localtorque', 'launch')['caption']);
    }

    public function testProfessionalTemplateCatalogueIsStable(): void
    {
        self::assertSame(
            ['editorial', 'field-guide', 'provider-spotlight', 'launch-impact'],
            array_keys(SocialMediaAssetService::templates())
        );
    }
}
