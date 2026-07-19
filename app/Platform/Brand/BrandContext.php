<?php

declare(strict_types=1);

namespace App\Platform\Brand;

use LogicException;

/**
 * Request/command-scoped brand holder for legacy static application boundaries.
 *
 * Domain services should prefer an explicit Brand argument. This holder exists
 * for templates, helpers, and framework code until dependency injection is
 * introduced incrementally.
 */
final class BrandContext
{
    private static ?Brand $current = null;

    public static function set(Brand $brand): void
    {
        self::$current = $brand;
    }

    public static function hasCurrent(): bool
    {
        return self::$current !== null;
    }

    public static function current(): Brand
    {
        if (self::$current === null) {
            throw new LogicException('Brand context has not been initialized');
        }
        return self::$current;
    }

    public static function clear(): void
    {
        self::$current = null;
    }
}
