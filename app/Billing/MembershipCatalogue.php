<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * Stable membership identifiers and agreed public pricing.
 *
 * Capability decisions still belong to PlanEntitlementService. This catalogue
 * supplies shared product language and seed defaults; it is not a payment
 * switch and cannot enable checkout or charging.
 */
final class MembershipCatalogue
{
    public const LAUNCH_ACCESS = 'launch_access';
    public const FREE_LISTING = 'free_listing';
    public const FOUNDING_VERIFIED = 'founding_verified';
    public const VERIFIED_PROVIDER = 'verified_provider';
    public const FEATURED_PROVIDER = 'featured_provider';

    /** @return array<int,string> */
    public static function slugs(): array
    {
        return [
            self::LAUNCH_ACCESS,
            self::FREE_LISTING,
            self::FOUNDING_VERIFIED,
            self::VERIFIED_PROVIDER,
            self::FEATURED_PROVIDER,
        ];
    }

    /** @return array{public_name:string,monthly_price_cents:int,annual_price_cents:int}|null */
    public static function find(string $slug): ?array
    {
        return self::definitions()[$slug] ?? null;
    }

    /** @return array<string,array{public_name:string,monthly_price_cents:int,annual_price_cents:int}> */
    public static function definitions(): array
    {
        return [
            self::LAUNCH_ACCESS => ['public_name' => 'Launch Access', 'monthly_price_cents' => 0, 'annual_price_cents' => 0],
            self::FREE_LISTING => ['public_name' => 'Free Listing', 'monthly_price_cents' => 0, 'annual_price_cents' => 0],
            self::FOUNDING_VERIFIED => ['public_name' => 'Founding Verified', 'monthly_price_cents' => 1000, 'annual_price_cents' => 10000],
            self::VERIFIED_PROVIDER => ['public_name' => 'Verified Provider', 'monthly_price_cents' => 1500, 'annual_price_cents' => 15000],
            self::FEATURED_PROVIDER => ['public_name' => 'Featured Provider', 'monthly_price_cents' => 2900, 'annual_price_cents' => 29000],
        ];
    }
}
