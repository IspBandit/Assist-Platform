<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * Canonical entitlement and limit keys. These are the ONLY strings the rest of
 * the application should reference when asking "may this provider do X?".
 *
 * Plans map these keys to values in billing_plan_features / billing_plan_limits,
 * and per-provider overrides may adjust them. Controllers must never hardcode
 * plan names or prices — they ask PlanEntitlementService about a key below.
 */
final class Entitlements
{
    // Boolean feature entitlements.
    public const CAN_CREATE_SERVICE_RUN            = 'can_create_service_run';
    public const CAN_VIEW_FULL_MATCHING_REQUESTS   = 'can_view_full_matching_requests';
    public const CAN_ACCESS_DEMAND_REPORTS         = 'can_access_demand_reports';
    public const CAN_BE_FEATURED                   = 'can_be_featured';
    public const CAN_EXPORT_DATA                   = 'can_export_data';
    public const CAN_USE_PRIORITY_MATCHING         = 'can_use_priority_matching';
    public const CAN_CREATE_CARAVAN_PARK_SERVICE_DAYS = 'can_create_caravan_park_service_days';
    public const CAN_ACCESS_ADVANCED_STATISTICS    = 'can_access_advanced_statistics';
    public const CAN_USE_CUSTOM_BRANDING           = 'can_use_custom_branding';
    public const CAN_ACCESS_API                    = 'can_access_api';

    // Numeric limits (null value = unlimited).
    public const MAX_ACTIVE_RUNS         = 'maximum_active_runs';
    public const MAX_SERVICE_CATEGORIES  = 'maximum_service_categories';
    public const MAX_SERVICE_AREAS       = 'maximum_service_areas';
    public const MAX_PROVIDER_USERS      = 'maximum_provider_users';
    public const MAX_BRANCHES            = 'maximum_branches';

    /** @return array<int,string> all boolean feature keys */
    public static function features(): array
    {
        return [
            self::CAN_CREATE_SERVICE_RUN,
            self::CAN_VIEW_FULL_MATCHING_REQUESTS,
            self::CAN_ACCESS_DEMAND_REPORTS,
            self::CAN_BE_FEATURED,
            self::CAN_EXPORT_DATA,
            self::CAN_USE_PRIORITY_MATCHING,
            self::CAN_CREATE_CARAVAN_PARK_SERVICE_DAYS,
            self::CAN_ACCESS_ADVANCED_STATISTICS,
            self::CAN_USE_CUSTOM_BRANDING,
            self::CAN_ACCESS_API,
        ];
    }

    /** @return array<int,string> all numeric limit keys */
    public static function limits(): array
    {
        return [
            self::MAX_ACTIVE_RUNS,
            self::MAX_SERVICE_CATEGORIES,
            self::MAX_SERVICE_AREAS,
            self::MAX_PROVIDER_USERS,
            self::MAX_BRANCHES,
        ];
    }

    /**
     * Maps a limit key to the usage-counter key that measures current usage.
     * @return array<string,string>
     */
    public static function limitCounterMap(): array
    {
        return [
            self::MAX_ACTIVE_RUNS        => UsageCounters::ACTIVE_RUNS,
            self::MAX_SERVICE_CATEGORIES => UsageCounters::SERVICE_CATEGORIES,
            self::MAX_SERVICE_AREAS      => UsageCounters::SERVICE_AREAS,
            self::MAX_PROVIDER_USERS     => UsageCounters::PROVIDER_USERS,
            self::MAX_BRANCHES           => UsageCounters::BRANCHES,
        ];
    }
}
