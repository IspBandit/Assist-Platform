<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class ProductBrandParityTest extends TestCase
{
    public function testStructuredFindDelegatesProductBrandsToSharedBrandDirectory(): void
    {
        $controller = $this->source('app/Controllers/Site/SearchController.php');

        self::assertStringContainsString("in_array(current_brand()->id(), ['towsmart', 'trailerwise'], true)", $controller);
        self::assertStringContainsString('return (new ProviderController())->index($request);', $controller);
    }

    public function testBrandDirectoryAcceptsPublicCategoryKeysWithoutCrossBrandLookup(): void
    {
        $controller = $this->source('app/Controllers/Site/ProviderController.php');

        self::assertStringContainsString('BrandProviderCategory::publicDirectorySql($brandId)', $controller);
        self::assertStringContainsString("AND category_key = ?", $controller);
    }

    public function testProductBrandSitemapsContainProviderAndTrustSurfaces(): void
    {
        $controller = $this->source('app/Controllers/Site/SitemapController.php');

        self::assertSame(2, substr_count($controller, '$this->addBrandProviders($urls, $brand->databaseId());'));
        self::assertSame(2, substr_count($controller, '$this->addBrandTrustPages($urls);'));
        self::assertStringContainsString("pbl.brand_id=? AND pbl.status='active' AND pbl.search_visible=1", $controller);
        self::assertStringContainsString("'accessibility-statement'", $controller);
    }

    public function testConfiguredLegalLinksUseLiveCmsRoutes(): void
    {
        $config = $this->source('config/brands.php');

        self::assertGreaterThanOrEqual(2, substr_count($config, "['label' => 'Privacy', 'path' => '/privacy-policy']"));
        self::assertGreaterThanOrEqual(2, substr_count($config, "['label' => 'Terms', 'path' => '/terms-of-use']"));
    }

    private function source(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2) . '/' . $path);
        self::assertIsString($source);
        return $source;
    }
}
