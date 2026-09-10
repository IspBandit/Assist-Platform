<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\AssetController;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use PHPUnit\Framework\TestCase;

final class AssetControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        BrandContext::clear();
    }

    public function testServesCurrentReleaseJavaScriptWithImmutableHeaders(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], []);
        $request->setRouteParams(['group' => 'js', 'name' => 'app.js']);

        $response = (new AssetController())->file($request);

        self::assertSame(200, $response->status());
        self::assertSame('application/javascript; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('public, max-age=31536000, immutable', $response->headers()['Cache-Control']);
    }

    public function testRejectsTraversalAndUnknownAssetGroups(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], []);
        $request->setRouteParams(['group' => 'uploads', 'name' => '..']);

        try {
            (new AssetController())->file($request);
            self::fail('Expected an asset 404');
        } catch (HttpException $exception) {
            self::assertSame(404, $exception->getStatusCode());
        }
    }

    public function testServesOnlyKnownBrandAssets(): void
    {
        $request = new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], []);
        $request->setRouteParams(['brand' => 'vanassist', 'name' => 'symbol-v2.svg']);

        $response = (new AssetController())->brand($request);

        self::assertSame(200, $response->status());
        self::assertSame('image/svg+xml', $response->headers()['Content-Type']);
    }

    public function testServesVanAssistManifestWithManifestHeaders(): void
    {
        BrandContext::set($this->brand('vanassist'));

        $response = (new AssetController())->manifest($this->request());

        self::assertSame(200, $response->status());
        self::assertSame('application/manifest+json; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('public, max-age=3600, must-revalidate', $response->headers()['Cache-Control']);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame('VanAssist', json_decode($response->content(), true, 512, JSON_THROW_ON_ERROR)['short_name']);
    }

    public function testServesTowSmartAndTrailerWiseManifests(): void
    {
        foreach (['towsmart' => 'TowSmart', 'trailerwise' => 'TrailerWise'] as $id => $shortName) {
            BrandContext::set($this->brand($id));

            $manifest = json_decode((new AssetController())->manifest($this->request())->content(), true, 512, JSON_THROW_ON_ERROR);

            self::assertSame($shortName, $manifest['short_name']);
            self::assertNotEmpty($manifest['icons']);
            self::assertNotEmpty($manifest['shortcuts']);
        }
    }

    public function testServesVanAssistWorkerWithUpdateSafeHeaders(): void
    {
        BrandContext::set($this->brand('vanassist'));

        $response = (new AssetController())->serviceWorker($this->request());

        self::assertSame(200, $response->status());
        self::assertSame('application/javascript; charset=UTF-8', $response->headers()['Content-Type']);
        self::assertSame('no-cache, no-store, must-revalidate', $response->headers()['Cache-Control']);
        self::assertSame('/', $response->headers()['Service-Worker-Allowed']);
        self::assertStringContainsString("self.addEventListener('install'", $response->content());
    }

    public function testPwaAssetsAreNotPublishedByOtherBrands(): void
    {
        BrandContext::set($this->brand('polaris'));

        foreach (['manifest', 'serviceWorker'] as $method) {
            try {
                (new AssetController())->{$method}($this->request());
                self::fail('Expected a PWA asset 404');
            } catch (HttpException $exception) {
                self::assertSame(404, $exception->getStatusCode());
            }
        }
    }

    private function request(): Request
    {
        return new Request([], [], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'], []);
    }

    private function brand(string $id): \App\Platform\Brand\Brand
    {
        $defaults = [
            'database_id' => 1,
            'name' => 'VanAssist',
            'legal_name' => 'Test Brand',
            'short_name' => 'VanAssist',
            'status' => 'active',
            'url' => 'https://' . $id . '.test',
            'domains' => ['primary' => $id . '.test'],
            'assets' => [
                'icon' => '/assets/brands/' . $id . '/symbol-v2.svg',
                'favicon' => '/assets/brands/' . $id . '/favicon.svg',
            ],
            'theme' => ['brand' => '#0f6e6e', 'surface' => '#fbf8f1'],
            'metadata' => ['description' => 'Test brand'],
            'contact' => [],
            'legal' => [],
            'navigation' => [],
            'footer' => [],
            'features' => [],
            'modules' => [],
            'analytics' => [],
            'search' => [],
            'storage_namespace' => $id,
        ];

        if ($id === 'towsmart') {
            $defaults['database_id'] = 2;
            $defaults['name'] = 'TowSmart';
            $defaults['short_name'] = 'TowSmart';
        } elseif ($id === 'trailerwise') {
            $defaults['database_id'] = 3;
            $defaults['name'] = 'TrailerWise';
            $defaults['short_name'] = 'TrailerWise';
        }

        return BrandRegistry::fromArray([
            $id => $defaults,
        ])->get($id);
    }
}
