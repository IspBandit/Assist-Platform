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

    public function testResolveModelYearPrefersCurrentThenNewest(): void
    {
        $years = [
            ['id' => 1, 'model_year' => 2025, 'production_status' => 'superseded'],
            ['id' => 2, 'model_year' => 2026, 'production_status' => 'current'],
        ];
        $default = CatalogueService::resolveModelYear($years, null);
        self::assertSame(2026, (int) $default['year']['model_year']);
        self::assertFalse($default['requested_invalid']);

        $picked = CatalogueService::resolveModelYear($years, 2025);
        self::assertSame(2025, (int) $picked['year']['model_year']);
        self::assertFalse($picked['requested_invalid']);

        $invalid = CatalogueService::resolveModelYear($years, 2019);
        self::assertSame(2026, (int) $invalid['year']['model_year']);
        self::assertTrue($invalid['requested_invalid']);
    }

    public function testModelYearSelectorWiring(): void
    {
        $root = dirname(__DIR__, 3);
        $controller = (string) file_get_contents($root . '/app/Controllers/Site/PolarisController.php');
        self::assertStringContainsString('resolveModelYear', $controller);
        self::assertStringContainsString('publishedYearsForModel', $controller);
        self::assertStringContainsString("'year'", $controller);

        $view = (string) file_get_contents($root . '/app/Views/polaris/model.php');
        self::assertStringContainsString('polaris-year-selector', $view);
        self::assertStringContainsString('year=', $view);

        $repo = (string) file_get_contents($root . '/app/Services/Polaris/CatalogueRepository.php');
        self::assertStringContainsString('function publishedYearsForModel', $repo);
        self::assertStringContainsString('model_year_id = ?', $repo);

        $sql = (string) file_get_contents($root . '/database/migrations/118_polaris_demo_southern_cross_2025_variant.sql');
        self::assertStringContainsString('18ft-island-bed-2025', $sql);
    }
}
