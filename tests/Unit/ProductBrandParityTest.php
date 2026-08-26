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

    public function testSharedDirectorySupportsServiceModelMatchingAndHonestRanking(): void
    {
        $controller = $this->source('app/Controllers/Site/ProviderController.php');
        $provider = $this->source('app/Models/Provider.php');
        $view = $this->source('app/Views/public/providers-index.php');
        $card = $this->source('app/Views/partials/provider-result-card.php');

        self::assertStringContainsString("in_array(\$serviceModel, ['mobile', 'workshop'], true)", $controller);
        self::assertStringContainsString("'service_type' => \$serviceModel !== '' ? \$serviceModel : 'either'", $controller);
        self::assertSame(2, substr_count($provider, "p.service_model IN (?, \\'both\\')"));
        self::assertStringContainsString('ps.is_inferred ASC', $provider);
        self::assertStringContainsString('pbca.is_verified DESC', $provider);
        self::assertStringContainsString('name="service_model"', $view);
        self::assertStringContainsString('array_merge(', $view);
        self::assertStringContainsString('Show mobile and workshop options', $view);
        self::assertStringContainsString('Verified for this service', $card);
        self::assertStringContainsString('Direct service match', $card);
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
