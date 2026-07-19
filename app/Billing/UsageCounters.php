<?php

declare(strict_types=1);

namespace App\Billing;

/**
 * Canonical usage-counter keys for plan-controlled actions. Usage is always
 * recalculated server-side from authoritative tables — never trusted from the
 * browser.
 */
final class UsageCounters
{
    public const ACTIVE_RUNS        = 'active_service_runs';
    public const PROVIDER_USERS     = 'provider_users';
    public const BRANCHES           = 'branch_locations';
    public const SERVICE_AREAS      = 'service_areas';
    public const SERVICE_CATEGORIES = 'service_categories';
    public const REQUEST_RESPONSES  = 'customer_request_responses';
    public const FEATURED_LISTINGS  = 'featured_listings';
    public const DATA_EXPORTS       = 'data_exports';
    public const NOTIFICATION_VOLUME = 'notification_volume';
    public const STORAGE_BYTES      = 'storage_bytes';

    /** @return array<int,string> */
    public static function all(): array
    {
        return [
            self::ACTIVE_RUNS,
            self::PROVIDER_USERS,
            self::BRANCHES,
            self::SERVICE_AREAS,
            self::SERVICE_CATEGORIES,
            self::REQUEST_RESPONSES,
            self::FEATURED_LISTINGS,
            self::DATA_EXPORTS,
            self::NOTIFICATION_VOLUME,
            self::STORAGE_BYTES,
        ];
    }
}
