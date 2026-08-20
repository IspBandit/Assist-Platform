<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Platform\Brand\Brand;

/**
 * Delivers immutable, application-owned presentation assets from the current
 * release. Paths are deliberately constrained to known directories and simple
 * filenames; uploads and other runtime data are never exposed here.
 */
final class AssetController extends Controller
{
    /** @var array<string,array<string,string>> */
    private const TYPES = [
        'css' => ['css' => 'text/css; charset=UTF-8'],
        'js' => ['js' => 'application/javascript; charset=UTF-8'],
        'img' => [
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ],
        'icons' => [
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ],
        'images' => [
            'avif' => 'image/avif',
            'gif' => 'image/gif',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'png' => 'image/png',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
        ],
    ];

    /** @var list<string> */
    private const BRANDS = ['vanassist', 'towsmart', 'trailerwise', 'localtorque'];

    /** @var list<string> */
    private const PWA_BRANDS = ['vanassist', 'towsmart', 'trailerwise'];

    public function manifest(Request $request): Response
    {
        $brand = current_brand();
        if (!in_array($brand->id(), self::PWA_BRANDS, true)) {
            $this->abort(404, 'Asset not found');
        }

        $payload = $this->manifestPayload($brand);

        return new Response(
            json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            200,
            [
                'Content-Type' => 'application/manifest+json; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600, must-revalidate',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function serviceWorker(Request $request): Response
    {
        if (!in_array(current_brand()->id(), self::PWA_BRANDS, true)) {
            $this->abort(404, 'Asset not found');
        }

        return $this->servePublicFile(
            'service-worker.js',
            'application/javascript; charset=UTF-8',
            'no-cache, no-store, must-revalidate',
            ['Service-Worker-Allowed' => '/']
        );
    }

    public function file(Request $request): Response
    {
        $group = (string) $request->route('group');

        return $this->serve($group, (string) $request->route('name'));
    }

    public function brand(Request $request): Response
    {
        $brand = (string) $request->route('brand');
        if (!in_array($brand, self::BRANDS, true)) {
            $this->abort(404, 'Asset not found');
        }

        return $this->serve('brands/' . $brand, (string) $request->route('name'), self::TYPES['img']);
    }

    /** @return array<string,mixed> */
    private function manifestPayload(Brand $brand): array
    {
        $brandId = $brand->id();
        $assets = $brand->assets();
        $metadata = $brand->metadata();
        $theme = $brand->theme();
        $surface = (string) ($theme['surface'] ?? '#fbf8f1');
        $brandColor = (string) ($theme['brand'] ?? '#0f6e6e');

        $payload = [
            'id' => '/',
            'name' => $brand->name() . ' — ' . ($metadata['description'] ?? $brand->name()),
            'short_name' => $brand->shortName(),
            'description' => (string) ($metadata['description'] ?? ''),
            'start_url' => '/?source=home-screen',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'any',
            'background_color' => $surface,
            'theme_color' => $brandColor,
            'icons' => $this->manifestIcons($brandId, $assets),
            'shortcuts' => $this->manifestShortcuts($brandId),
        ];

        if ($brandId === 'vanassist') {
            $payload['categories'] = ['travel', 'navigation', 'lifestyle'];
        } elseif ($brandId === 'towsmart') {
            $payload['categories'] = ['utilities', 'productivity'];
        } else {
            $payload['categories'] = ['business', 'utilities'];
        }

        return $payload;
    }

    /**
     * @param array<string,string> $assets
     * @return list<array<string,string>>
     */
    private function manifestIcons(string $brandId, array $assets): array
    {
        if ($brandId === 'vanassist') {
            return [
                ['src' => '/assets/brands/vanassist/install-icon.svg', 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
                ['src' => '/assets/brands/vanassist/install-icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => '/assets/brands/vanassist/install-icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ];
        }

        $icon = (string) ($assets['icon'] ?? $assets['logo'] ?? $assets['favicon'] ?? '/assets/brands/' . $brandId . '/symbol-v2.svg');

        return [
            ['src' => $icon, 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any maskable'],
            ['src' => (string) ($assets['favicon'] ?? $icon), 'sizes' => 'any', 'type' => 'image/svg+xml', 'purpose' => 'any'],
        ];
    }

    /** @return list<array<string,string>> */
    private function manifestShortcuts(string $brandId): array
    {
        return match ($brandId) {
            'towsmart' => [
                ['name' => 'Weight calculator', 'short_name' => 'Calculator', 'url' => '/calculator'],
                ['name' => 'Tow guide', 'short_name' => 'Tow guide', 'url' => '/tow-guide'],
                ['name' => 'My combinations', 'short_name' => 'Combinations', 'url' => '/account/towing-combinations'],
            ],
            'trailerwise' => [
                ['name' => 'Find trailer services', 'short_name' => 'Find services', 'url' => '/providers'],
                ['name' => 'Service categories', 'short_name' => 'Categories', 'url' => '/services'],
                ['name' => 'Register a business', 'short_name' => 'For business', 'url' => '/for-providers'],
            ],
            default => [
                ['name' => 'Find nearby help', 'short_name' => 'Find help', 'url' => '/find'],
                ['name' => 'Places to stay', 'short_name' => 'Stays', 'url' => '/stays'],
                ['name' => 'Request assistance', 'short_name' => 'Request help', 'url' => '/request-assistance'],
            ],
        };
    }

    /** @param array<string,string> $headers */
    private function servePublicFile(string $name, string $contentType, string $cacheControl, array $headers = []): Response
    {
        $file = base_path('public/' . $name);
        $content = is_file($file) ? file_get_contents($file) : false;
        if ($content === false) {
            $this->abort(404, 'Asset not found');
        }

        return new Response($content, 200, $headers + [
            'Content-Type' => $contentType,
            'Cache-Control' => $cacheControl,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /** @param array<string,string>|null $types */
    private function serve(string $directory, string $name, ?array $types = null): Response
    {
        $types ??= self::TYPES[$directory] ?? null;
        if ($types === null || preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) !== 1) {
            $this->abort(404, 'Asset not found');
        }

        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!isset($types[$extension])) {
            $this->abort(404, 'Asset not found');
        }

        $assetRoot = realpath(base_path('public/assets'));
        $file = realpath(base_path('public/assets/' . $directory . '/' . $name));
        if ($assetRoot === false || $file === false || !is_file($file)) {
            $this->abort(404, 'Asset not found');
        }

        $prefix = rtrim(str_replace('\\', '/', $assetRoot), '/') . '/';
        if (!str_starts_with(str_replace('\\', '/', $file), $prefix)) {
            $this->abort(404, 'Asset not found');
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->abort(404, 'Asset not found');
        }

        return new Response($content, 200, [
            'Content-Type' => $types[$extension],
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
