<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\AssetController;
use App\Core\Request;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use PHPUnit\Framework\TestCase;

final class VanAssistInstallTest extends TestCase
{
    protected function tearDown(): void
    {
        \App\Platform\Brand\BrandContext::clear();
        parent::tearDown();
    }

    public function testPublicShellPublishesInstallMetadataForProductBrands(): void
    {
        $layout = $this->source('app/Views/layouts/public.php');
        self::assertStringContainsString("'vanassist', 'towsmart', 'trailerwise'", $layout);
        self::assertStringContainsString('manifest.webmanifest', $layout);
        self::assertStringContainsString('apple-touch-icon', $layout);
        self::assertStringContainsString('data-brand=', $layout);
    }

    public function testMobileInstallControlSupportsAndroidAndAppleInstructions(): void
    {
        $footer = $this->source('app/Views/partials/footer.php');
        $script = $this->source('public/assets/js/app.js');
        self::assertStringContainsString('Save VanAssist to your phone', $footer);
        self::assertStringContainsString('Save TowSmart to your phone', $footer);
        self::assertStringContainsString('Save TrailerWise to your phone', $footer);
        self::assertStringContainsString('Add to Home Screen', $footer);
        self::assertStringContainsString('data-install-desktop', $footer);
        self::assertStringContainsString('beforeinstallprompt', $script);
        self::assertStringContainsString("var androidMobile = /Android/i", $script);
        self::assertStringContainsString("installBrand === 'vanassist' || installBrand === 'towsmart' || installBrand === 'trailerwise'", $script);
        self::assertStringContainsString("serviceWorker.register('/service-worker.js')", $script);
        self::assertStringContainsString('display-mode: standalone', $script);
    }

    public function testApprovedVanAssistWordmarkUsesSuppliedRoadDirectionAndExactLine(): void
    {
        $header = $this->source('app/Views/partials/header.php');
        $brands = $this->source('config/brands.php');
        self::assertStringContainsString('class="vanassist-road-wordmark"', $header);
        self::assertStringContainsString('brands/vanassist/wordmark-road.png', $header);
        self::assertStringContainsString('Find. Connect. Get Assisted.', $header);
        self::assertStringContainsString('FIND · CONNECT · GET ASSISTED', $brands);
        self::assertStringNotContainsString('class="brand-road"', $header);
    }

    public function testHomepageUsesTravelCompanionIdentityAndCoastalResponsiveHero(): void
    {
        $home = $this->source('app/Views/public/home.php');
        self::assertStringContainsString('Your travel', $home);
        self::assertStringContainsString('companion.', $home);
        self::assertStringContainsString('Wherever the road takes you.', $home);
        self::assertStringContainsString('vanassist-coastal-hero-desktop-v1.webp', $home);
        self::assertStringContainsString('vanassist-coastal-hero-mobile-v1.webp', $home);
        self::assertStringContainsString('Save VanAssist before you go', $home);
    }

    public function testManifestAndWorkerAvoidCachingPrivatePages(): void
    {
        BrandContext::set($this->brand());
        $manifest = json_decode((new AssetController())->manifest(new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], []))->content(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('standalone', $manifest['display']);
        self::assertSame('VanAssist', $manifest['short_name']);
        self::assertGreaterThanOrEqual(3, count($manifest['icons']));
        $worker = $this->source('public/service-worker.js');
        self::assertStringContainsString("url.pathname.startsWith('/assets/')", $worker);
        self::assertStringNotContainsString("cache.add('/')", $worker);
        self::assertStringContainsString('assist-platform-static-v1', $worker);
    }

    private function brand(): \App\Platform\Brand\Brand
    {
        return BrandRegistry::fromArray([
            'vanassist' => [
                'database_id' => 1,
                'name' => 'VanAssist',
                'legal_name' => 'VanAssist',
                'short_name' => 'VanAssist',
                'status' => 'active',
                'url' => 'https://vanassist.test',
                'domains' => ['primary' => 'vanassist.test'],
                'assets' => [],
                'theme' => ['brand' => '#0f6e6e', 'surface' => '#fbf8f1'],
                'metadata' => ['description' => 'Find caravan and RV help.'],
                'contact' => [],
                'legal' => [],
                'navigation' => [],
                'footer' => [],
                'features' => [],
                'modules' => [],
                'analytics' => [],
                'search' => [],
                'storage_namespace' => 'vanassist',
            ],
        ])->get('vanassist');
    }

    public function testManifestAndWorkerHaveExplicitApplicationRoutes(): void
    {
        $routes = $this->source('routes/web.php');

        self::assertStringContainsString("'/manifest.webmanifest', 'Site\\AssetController@manifest'", $routes);
        self::assertStringContainsString("'/service-worker.js', 'Site\\AssetController@serviceWorker'", $routes);
    }

    private function source(string $relativePath): string
    {
        $contents = file_get_contents(dirname(__DIR__, 2) . '/' . $relativePath);
        self::assertIsString($contents);
        return $contents;
    }
}
