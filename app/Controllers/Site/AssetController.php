<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;

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
