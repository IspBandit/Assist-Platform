<?php

declare(strict_types=1);

namespace Tests\Unit\Polaris;

use App\Services\Polaris\CatalogueService;
use PHPUnit\Framework\TestCase;

final class CatalogueServiceTest extends TestCase
{
    public function testPayloadIsAtmMinusTare(): void
    {
        self::assertSame(650, CatalogueService::payloadKg(1850, 2500));
    }

    public function testPayloadNullWhenIncompleteOrInvalid(): void
    {
        self::assertNull(CatalogueService::payloadKg(null, 2500));
        self::assertNull(CatalogueService::payloadKg(1850, null));
        self::assertNull(CatalogueService::payloadKg(2500, 1850));
    }

    public function testPriceFormatting(): void
    {
        self::assertSame('From $89,900', CatalogueService::formatPrice('from', 8990000));
        self::assertSame('RRP $112,000', CatalogueService::formatPrice('rrp', 11200000));
        self::assertSame('Contact dealer', CatalogueService::formatPrice('contact_dealer', null));
        self::assertSame('Price unavailable', CatalogueService::formatPrice('unknown', null));
        self::assertSame('Indicative $38,500', CatalogueService::formatPrice('indicative', 3850000));
    }

    public function testCategoryLabelsCoverCoreRvTypes(): void
    {
        $labels = CatalogueService::categoryLabels();
        self::assertArrayHasKey('caravan', $labels);
        self::assertArrayHasKey('hybrid_caravan', $labels);
        self::assertArrayHasKey('slide_on', $labels);
        self::assertSame('Hybrid caravan', CatalogueService::categoryLabel('hybrid_caravan'));
    }
}
