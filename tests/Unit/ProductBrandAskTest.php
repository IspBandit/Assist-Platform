<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\ProductBrandAsk;
use PHPUnit\Framework\TestCase;

final class ProductBrandAskTest extends TestCase
{
    public function testTowSmartCalculationIntentKeepsSafetyBoundary(): void
    {
        $result = (new ProductBrandAsk())->resolve('towsmart', 'Can I tow this weight safely?');

        self::assertSame('calculator', $result['kind']);
        self::assertStringContainsString('not certification', $result['explanation']);
    }

    public function testTowSmartProviderIntentMapsToCuratedCategoryAndLocation(): void
    {
        $result = (new ProductBrandAsk())->resolve('towsmart', 'mobile weighing near Toowoomba');

        self::assertSame('public-weighing', $result['category']);
        self::assertSame('Toowoomba', $result['location']);
        self::assertStringContainsString('category=public-weighing', $result['url']);

        self::assertSame('towbars-hitches', (new ProductBrandAsk())->resolve('towsmart', 'towbar near Ipswich')['category']);
        self::assertSame('brakes-controllers', (new ProductBrandAsk())->resolve('towsmart', 'brake controller near Gympie')['category']);
        self::assertSame('suspension-payload', (new ProductBrandAsk())->resolve('towsmart', 'suspension specialist near Mackay')['category']);
    }

    public function testNearMeNeverBecomesAFalseTownName(): void
    {
        $service = new ProductBrandAsk();

        $missing = $service->resolve('towsmart', 'mobile weighing near me');
        self::assertSame('location', $missing['kind']);
        self::assertNull($missing['location']);
        self::assertStringNotContainsString('location=Me', $missing['url']);

        $resolved = $service->resolve('towsmart', 'mobile weighing near me', 'Brisbane City, QLD');
        self::assertSame('providers', $resolved['kind']);
        self::assertSame('public-weighing', $resolved['category']);
        self::assertSame('Brisbane City, QLD', $resolved['location']);
        self::assertStringContainsString('location=Brisbane+City%2C+QLD', $resolved['url']);
    }

    public function testTrailerWiseRepresentativeJourneysMapDeterministically(): void
    {
        $service = new ProductBrandAsk();
        $expected = [
            'repair my trailer near Cairns' => 'trailer-repairs',
            'find trailer parts near Dubbo' => 'parts-accessories',
            'roadworthy certifier near Hobart' => 'roadworthy-inspections',
            'trailer manufacturer near Brisbane' => 'manufacturers-dealers',
            'mobile trailer service near Bendigo' => 'mobile-trailer-services',
        ];
        foreach ($expected as $query => $category) {
            self::assertSame($category, $service->resolve('trailerwise', $query)['category'], $query);
        }
    }

    public function testTrailerWiseNearMeUsesDeviceLocation(): void
    {
        $result = (new ProductBrandAsk())->resolve('trailerwise', 'trailer bearings near current location', 'Gladstone, QLD');

        self::assertSame('providers', $result['kind']);
        self::assertSame('tyres-wheels-bearings', $result['category']);
        self::assertSame('Gladstone, QLD', $result['location']);
    }

    public function testUnknownIntentDoesNotSubstituteUnrelatedProvider(): void
    {
        $result = (new ProductBrandAsk())->resolve('trailerwise', 'something unusual near Orange');

        self::assertSame('clarify', $result['kind']);
        self::assertNull($result['category']);
        self::assertStringContainsString('no unrelated business', $result['explanation']);
    }

    public function testBrandEducationAndOwnershipQuestionsUseGuidanceRoutes(): void
    {
        $service = new ProductBrandAsk();

        self::assertSame('guidance', $service->resolve('towsmart', 'What does GCM mean?')['kind']);
        self::assertStringContainsString('/tow-guide', $service->resolve('towsmart', 'What does GCM mean?')['url']);
        self::assertSame('guidance', $service->resolve('trailerwise', 'trailer registration rules')['kind']);
        self::assertStringContainsString('/rules', $service->resolve('trailerwise', 'trailer registration rules')['url']);
    }
}
