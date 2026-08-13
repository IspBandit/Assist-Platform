<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class BrandServiceCategoriesTest extends TestCase
{
    public function testCategoryControllerRoutesTowSmartAndTrailerWiseThroughBrandDirectory(): void
    {
        $source = (string) file_get_contents(base_path('app/Controllers/Site/CategoryController.php'));

        self::assertStringContainsString("in_array(current_brand()->id(), ['localtorque', 'towsmart', 'trailerwise'], true)", $source);
        self::assertStringContainsString('brandDirectoryIndex()', $source);
        self::assertStringContainsString('brandDirectoryShow($request, $slug)', $source);
        self::assertStringContainsString("'brands.service-categories'", $source);
        self::assertStringContainsString('FROM brand_provider_categories WHERE brand_id = ?', $source);
        self::assertStringContainsString('Provider::brandDirectory', $source);
    }

    public function testBrandServiceCategoryViewLinksToDirectorySearch(): void
    {
        $view = (string) file_get_contents(base_path('app/Views/brands/service-categories.php'));

        self::assertStringContainsString("url('providers?category='", $view);
        self::assertStringContainsString("url('services/' . \$item['slug'])", $view);
        self::assertStringNotContainsString('Caravan & RV', $view);
    }

    public function testTowSmartHomeSurfacesLinkedSpecialistCategories(): void
    {
        $home = (string) file_get_contents(base_path('app/Views/towsmart/home.php'));
        $controller = (string) file_get_contents(base_path('app/Controllers/Site/TowSmartController.php'));

        self::assertStringContainsString('service-tile-link', $home);
        self::assertStringContainsString("url('services/' . \$category['slug'])", $home);
        self::assertStringContainsString("url('providers')", $home);
        self::assertStringContainsString('Find specialists', $home);
        self::assertStringContainsString('brandCategories()', $controller);
        self::assertStringNotContainsString('being built', $home);
    }

    public function testTrailerWiseHomeUsesPresentTenseDiscoveryCopy(): void
    {
        $home = (string) file_get_contents(base_path('app/Views/trailerwise/home.php'));
        $controller = (string) file_get_contents(base_path('app/Controllers/Site/TrailerWiseController.php'));

        self::assertStringContainsString('service-tile-link', $home);
        self::assertStringContainsString("url('services/' . \$category['slug'])", $home);
        self::assertStringContainsString("url('marketplace')", $home);
        self::assertStringContainsString('Browse categories', $home);
        self::assertStringContainsString('brandCategories()', $controller);
        self::assertStringNotContainsString('being built', strtolower($home));
    }

    public function testBrandNavigationAndSitemapExposeServiceDiscovery(): void
    {
        $brands = (string) file_get_contents(base_path('config/brands.php'));
        $sitemap = (string) file_get_contents(base_path('app/Controllers/Site/SitemapController.php'));

        self::assertStringContainsString("'Specialist categories', 'path' => '/services'", $brands);
        self::assertStringContainsString("'Marketplace', 'path' => '/marketplace'", $brands);
        self::assertStringContainsString("url('tow-guide')", $sitemap);
        self::assertStringContainsString("url('checklist')", $sitemap);
        self::assertStringContainsString("'services/', 0.7, [\$brand->databaseId()]", $sitemap);
    }
}
