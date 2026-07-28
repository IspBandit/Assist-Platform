<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Controllers\Site\AssetController;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use PHPUnit\Framework\TestCase;

final class AssetControllerTest extends TestCase
{
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
}
