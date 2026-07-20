<?php

declare(strict_types=1);

namespace App\Platform\Brand;

/** Fail-closed public route policy for independently branded domains. */
final class BrandRoutePolicy
{
    public function allows(Brand $brand, string $path): bool
    {
        if ($brand->id() === 'vanassist') {
            return true;
        }

        if (in_array($path, ['/', '/sitemap.xml', '/robots.txt'], true)) {
            return true;
        }

        if ($brand->id() === 'towsmart' && $brand->moduleEnabled('towing_tools')) {
            return $path === '/tools' || str_starts_with($path, '/commercial/go/');
        }

        if ($brand->id() === 'trailerwise' && $brand->moduleEnabled('trailer_marketplace')) {
            return $path === '/marketplace' || str_starts_with($path, '/marketplace/');
        }

        return false;
    }
}
