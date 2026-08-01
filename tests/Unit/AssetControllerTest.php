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
        BrandContext::set($this->brand('towsmart'));

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
        return BrandRegistry::fromArray([
            $id => [
                'database_id' => $id === 'vanassist' ? 1 : 2,
                'name' => $id === 'vanassist' ? 'VanAssist' : 'TowSmart',
                'legal_name' => 'Test Brand',
                'short_name' => 'Test',
                'status' => 'active',
                'url' => 'https://' . $id . '.test',
                'domains' => ['primary' => $id . '.test'],
                'assets' => [],
                'theme' => [],
                'metadata' => [],
                'contact' => [],
                'legal' => [],
                'storage_namespace' => $id,
            ],
        ])->get($id);
    }
}
