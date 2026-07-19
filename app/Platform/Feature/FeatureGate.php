<?php

declare(strict_types=1);

namespace App\Platform\Feature;

use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;

/**
 * Typed brand feature evaluation.
 *
 * This gate describes product availability only. Callers must still enforce
 * authentication, permissions, memberships, ownership, and entitlements.
 */
final class FeatureGate
{
    public static function enabled(PlatformFeature $feature, ?Brand $brand = null): bool
    {
        $brand ??= BrandContext::current();

        return $brand->featureEnabled($feature->value);
    }
}
