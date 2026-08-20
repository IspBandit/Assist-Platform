<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\View;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Services\Settings;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

final class ProductBrandShellTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
        $this->setSettingsCache(null);
        parent::tearDown();
    }

    public function testTowSmartHeaderAndFooterSurfacePrimaryJourneys(): void
    {
        BrandContext::set($this->brand('towsmart'));

        $header = View::render('partials.header');
        $footer = View::render('partials.footer');

        self::assertStringContainsString('Check weights', $header);
        self::assertStringContainsString('/calculator', $header);
        self::assertStringContainsString('footer-action', $footer);
        self::assertStringContainsString('Check my combination', $footer);
        self::assertStringContainsString('Weight calculator', $footer);
        self::assertStringContainsString('My combinations', $footer);
        self::assertStringContainsString('Save TowSmart to your phone', $footer);
        self::assertStringContainsString('data-install-app', $footer);
    }

    public function testTrailerWiseHeaderAndFooterSurfacePrimaryJourneys(): void
    {
        BrandContext::set($this->brand('trailerwise'));

        $header = View::render('partials.header');
        $footer = View::render('partials.footer');

        self::assertStringContainsString('Find services', $header);
        self::assertStringContainsString('/providers', $header);
        self::assertStringContainsString('footer-action', $footer);
        self::assertStringContainsString('Find trailer services', $footer);
        self::assertStringContainsString('Service categories', $footer);
        self::assertStringContainsString('Sale and hire listings', $footer);
        self::assertStringContainsString('Save TrailerWise to your phone', $footer);
    }

    public function testPublicLayoutPublishesInstallMetadataForProductBrands(): void
    {
        foreach (['towsmart', 'trailerwise'] as $brandId) {
            BrandContext::set($this->brand($brandId));

            $html = View::render('layouts.public', ['title' => 'Home']);

            self::assertStringContainsString('manifest.webmanifest', $html);
            self::assertStringContainsString('apple-touch-icon', $html);
            self::assertStringContainsString('apple-mobile-web-app-title', $html);
        }
    }

    private function brand(string $id): \App\Platform\Brand\Brand
    {
        $configs = [
            'towsmart' => [
                'database_id' => 2,
                'name' => 'TowSmart',
                'legal_name' => 'TowSmart',
                'short_name' => 'TowSmart',
                'status' => 'active',
                'url' => 'https://towsmart.test',
                'domains' => ['primary' => 'towsmart.test'],
                'assets' => [
                    'logo' => '/assets/brands/towsmart/symbol-v2.svg',
                    'icon' => '/assets/brands/towsmart/symbol-v2.svg',
                    'favicon' => '/assets/brands/towsmart/favicon.svg',
                ],
                'theme' => ['brand' => '#1d4ed8', 'surface' => '#eff6ff'],
                'metadata' => [
                    'wordmark_prefix' => 'Tow',
                    'wordmark_accent' => 'Smart',
                    'header_descriptor' => 'TOWING SAFETY & GUIDANCE',
                    'tagline' => 'Tow smarter. Tow safer.',
                    'description' => 'Towing calculations, compatibility, education and safety tools.',
                ],
                'contact' => ['support_email' => 'support@towsmart.test'],
                'legal' => [],
                'navigation' => [
                    ['label' => 'Weight calculator', 'path' => '/calculator'],
                    ['label' => 'Tow guide', 'path' => '/tow-guide'],
                ],
                'footer' => [],
                'features' => [],
                'modules' => ['public_application' => true],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'towwise',
            ],
            'trailerwise' => [
                'database_id' => 3,
                'name' => 'TrailerWise',
                'legal_name' => 'TrailerWise',
                'short_name' => 'TrailerWise',
                'status' => 'active',
                'url' => 'https://trailerwise.test',
                'domains' => ['primary' => 'trailerwise.test'],
                'assets' => [
                    'logo' => '/assets/brands/trailerwise/symbol-v2.svg',
                    'icon' => '/assets/brands/trailerwise/symbol-v2.svg',
                    'favicon' => '/assets/brands/trailerwise/favicon.svg',
                ],
                'theme' => ['brand' => '#7c3aed', 'surface' => '#faf5ff'],
                'metadata' => [
                    'wordmark_prefix' => 'Trailer',
                    'wordmark_accent' => 'Wise',
                    'header_descriptor' => 'TRAILER SERVICE NETWORK',
                    'tagline' => 'Smarter trailer ownership.',
                    'description' => 'Trailer repairers, service centres, parts, inspections, compliance and ownership resources.',
                ],
                'contact' => ['support_email' => 'support@trailerwise.test'],
                'legal' => [],
                'navigation' => [
                    ['label' => 'Find trailer services', 'path' => '/providers'],
                    ['label' => 'Service categories', 'path' => '/services'],
                ],
                'footer' => [],
                'features' => [],
                'modules' => ['public_application' => true],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'trailerwise',
            ],
        ];

        return BrandRegistry::fromArray([$id => $configs[$id]])->get($id);
    }

    /** @param array<string,string>|null $values */
    private function setSettingsCache(?array $values): void
    {
        $property = new ReflectionProperty(Settings::class, 'cache');
        $property->setValue(null, $values);
    }
}
