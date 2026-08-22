<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\BrandProviderCategory;
use PHPUnit\Framework\TestCase;

final class BrandProviderCategoryTest extends TestCase
{
    public function testTowSmartAndTrailerWiseUseCuratedPublicDirectoryThreshold(): void
    {
        self::assertTrue(BrandProviderCategory::usesCuratedPublicDirectory(2));
        self::assertTrue(BrandProviderCategory::usesCuratedPublicDirectory(3));
        self::assertFalse(BrandProviderCategory::usesCuratedPublicDirectory(4));
    }

    public function testPublicDirectorySqlExcludesImportOnlyTaxonomyRowsForProductBrands(): void
    {
        self::assertStringContainsString('sort_order < 100', BrandProviderCategory::publicDirectorySql(2));
        self::assertSame('brand_id = ? AND is_active = 1', BrandProviderCategory::publicDirectorySql(4));
    }

    public function testPublicControllersUseCuratedDirectoryFilter(): void
    {
        foreach ([
            'app/Controllers/Site/CategoryController.php',
            'app/Controllers/Site/TowSmartController.php',
            'app/Controllers/Site/TrailerWiseController.php',
            'app/Controllers/Site/ProviderController.php',
            'app/Controllers/Site/SitemapController.php',
        ] as $file) {
            $source = (string) file_get_contents(base_path($file));
            self::assertStringContainsString('BrandProviderCategory::publicDirectorySql', $source, $file);
        }
    }

    public function testLocalTorqueSeederPreservesCuratedCategoryCopy(): void
    {
        $source = (string) file_get_contents(base_path('app/Services/LocalTorquePackSeeder.php'));

        self::assertStringContainsString('name=IF(sort_order < 100, name, VALUES(name))', $source);
        self::assertStringContainsString('description=IF(sort_order < 100, description, VALUES(description))', $source);
    }

    public function testMigrationRestoresCuratedTowSmartAndTrailerWiseCategories(): void
    {
        $sql = (string) file_get_contents(base_path('database/migrations/130_restore_curated_brand_directory_categories.sql'));

        self::assertStringContainsString("category_key = 'public-weighing'", $sql);
        self::assertStringContainsString("category_key = 'mobile-trailer-services'", $sql);
        self::assertStringContainsString('sort_order = 10', $sql);
    }
}
