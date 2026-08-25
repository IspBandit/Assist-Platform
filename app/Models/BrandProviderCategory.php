<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Brand-scoped provider directory categories.
 *
 * TowSmart and TrailerWise expose curated public categories (migration 038,
 * sort_order below 100). Provider-pack taxonomy imports use sort_order 100 and
 * remain available for classification and admin workflows only.
 */
final class BrandProviderCategory
{
    /** @var list<int> */
    private const CURATED_PUBLIC_BRAND_IDS = [2, 3];

    public static function usesCuratedPublicDirectory(int $brandId): bool
    {
        return in_array($brandId, self::CURATED_PUBLIC_BRAND_IDS, true);
    }

    public static function publicDirectorySql(int $brandId): string
    {
        $sql = 'brand_id = ? AND is_active = 1';
        if (self::usesCuratedPublicDirectory($brandId)) {
            $sql .= ' AND sort_order < 100';
        }

        return $sql;
    }

    /** @return list<int|string> */
    public static function publicDirectoryParams(int $brandId): array
    {
        return [$brandId];
    }
}
