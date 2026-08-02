<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Exceptions\AdminApiException;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;

/**
 * Resolves Admin API data scope from verified host/deployment brand context.
 */
final class AdminApiBrandScope
{
    public static function brand(): Brand
    {
        if (!BrandContext::hasCurrent()) {
            throw new AdminApiException(
                500,
                'internal_error',
                'Brand context is not initialized for this request.'
            );
        }

        return BrandContext::current();
    }

    public static function brandId(): int
    {
        return self::brand()->databaseId();
    }

    public static function staysEnabled(): bool
    {
        return self::brand()->moduleEnabled('parks');
    }

    public static function assertStaysEnabled(): void
    {
        if (!self::staysEnabled()) {
            throw new AdminApiException(
                404,
                'not_found',
                'Stays are not available for this brand scope.'
            );
        }
    }
}
