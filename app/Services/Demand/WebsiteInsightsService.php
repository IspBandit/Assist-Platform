<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Database;

/**
 * Brand-scoped, aggregate read model for the operator-facing website insights
 * dashboard. It never exposes anonymous visitor identity or raw event metadata.
 *
 * Optional filters (Admin API query params, echoed under `filters`):
 * - location: case-insensitive town name or postcode match on search demand
 * - device: mobile|tablet|desktop — genuine device only (never mixes bot/unknown
 *   into genuine totals)
 *
 * Additional daily series (backwards-compatible additive fields):
 * - daily_searches / daily_contacts / daily_profile_views
 */
final class WebsiteInsightsService
{
    private const GENUINE_DEVICES = ['mobile', 'tablet', 'desktop'];

    /**
     * @param array{location?:string,device?:string} $filters
     * @return array<string,mixed>
     */
    public static function report(int $brandId, string $from, string $to, array $filters = []): array
    {
        [$start, $end] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $location = self::normaliseLocation($filters['location'] ?? null);
        $device = self::normaliseDevice($filters['device'] ?? null);

        $pageDeviceSql = self::pageDeviceSql($device);
        $eventDeviceSql = self::eventDeviceSql($device);
        $searchDeviceSql = self::searchDeviceJoinSql($device);
        $contactDeviceSql = self::contactDeviceJoinSql($device);
        $locationSql = self::locationSql($location);

        $pageParams = self::pageParams($brandId, $start, $end, $device);
        $eventParams = self::eventParams($brandId, $start, $end, $device);
        $searchParams = self::searchParams($brandId, $start, $end, $device, $location);
        $contactParams = self::contactParams($brandId, $start, $end, $device);
        $outcomeParams = [$brandId, $start, $end];

        $views = self::count(
            "SELECT COUNT(*) FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND created_at BETWEEN ? AND ?",
            $pageParams
        );
        $visitors = self::count(
            "SELECT COUNT(DISTINCT session_id) FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND session_id IS NOT NULL AND created_at BETWEEN ? AND ?",
            $pageParams
        );
        $signedIn = self::count(
            "SELECT COUNT(DISTINCT user_id) FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND user_id IS NOT NULL AND created_at BETWEEN ? AND ?",
            $pageParams
        );
        $searches = self::count(
            'SELECT COUNT(*) FROM provider_searches ps '
            . $searchDeviceSql
            . ' LEFT JOIN towns t ON t.id=ps.town_id '
            . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.created_at BETWEEN ? AND ?{$locationSql}",
            $searchParams
        );
        $noResults = self::count(
            'SELECT COUNT(*) FROM provider_searches ps '
            . $searchDeviceSql
            . ' LEFT JOIN towns t ON t.id=ps.town_id '
            . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.result_count=0 AND ps.created_at BETWEEN ? AND ?{$locationSql}",
            $searchParams
        );
        $profileViews = self::count(
            "SELECT COUNT(*) FROM analytics_events WHERE brand_id=? AND event_name='provider_profile_viewed' AND is_excluded=0 AND {$eventDeviceSql} AND created_at BETWEEN ? AND ?",
            $eventParams
        );
        $contacts = self::count(
            'SELECT COUNT(*) FROM provider_contact_actions pca '
            . $contactDeviceSql
            . ' WHERE pca.brand_id=? AND pca.is_excluded=0 AND pca.created_at BETWEEN ? AND ?',
            $contactParams
        );
        $confirmed = self::count(
            "SELECT COUNT(*) FROM service_outcomes WHERE brand_id=? AND is_excluded=0 AND confidence IN ('customer_reported','both_confirmed','admin_verified') AND created_at BETWEEN ? AND ?",
            $outcomeParams
        );
        $lastPageView = Database::scalar(
            "SELECT MAX(created_at) FROM page_views WHERE brand_id=? AND device_type NOT IN ('bot','unknown')",
            [$brandId]
        );
        $lastDemandEvent = Database::scalar(
            'SELECT MAX(created_at) FROM analytics_events WHERE brand_id=? AND is_excluded=0',
            [$brandId]
        );

        return [
            'summary' => [
                'page_views' => $views,
                'visitors' => $visitors,
                'signed_in_visitors' => $signedIn,
                'pages_per_visitor' => $visitors > 0 ? round($views / $visitors, 1) : null,
                'searches' => $searches,
                'no_results' => $noResults,
                'profile_views' => $profileViews,
                'contact_actions' => $contacts,
                'confirmed_uses' => $confirmed,
                'successful_searches' => max(0, $searches - $noResults),
                'search_success_rate' => ReportingService::rate(max(0, $searches - $noResults), $searches),
                'search_to_contact' => ReportingService::rate($contacts, $searches),
                'profile_to_contact' => ReportingService::rate($contacts, $profileViews),
                'last_page_view_at' => is_string($lastPageView) ? $lastPageView : null,
                'last_demand_event_at' => is_string($lastDemandEvent) ? $lastDemandEvent : null,
            ],
            'daily' => self::rows(
                'SELECT DATE(created_at) AS label, COUNT(*) AS total, COUNT(DISTINCT session_id) AS secondary '
                . "FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND created_at BETWEEN ? AND ? GROUP BY DATE(created_at) ORDER BY label",
                $pageParams
            ),
            // Additive series for Assist RIC Trends (optional for older clients).
            'daily_searches' => self::rows(
                'SELECT DATE(ps.created_at) AS label, COUNT(*) AS total, SUM(ps.result_count=0) AS secondary '
                . 'FROM provider_searches ps '
                . $searchDeviceSql
                . ' LEFT JOIN towns t ON t.id=ps.town_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.created_at BETWEEN ? AND ?{$locationSql} "
                . 'GROUP BY DATE(ps.created_at) ORDER BY label',
                $searchParams
            ),
            'daily_contacts' => self::rows(
                'SELECT DATE(pca.created_at) AS label, COUNT(*) AS total, COUNT(DISTINCT pca.session_id) AS secondary '
                . 'FROM provider_contact_actions pca '
                . $contactDeviceSql
                . ' WHERE pca.brand_id=? AND pca.is_excluded=0 AND pca.created_at BETWEEN ? AND ? '
                . 'GROUP BY DATE(pca.created_at) ORDER BY label',
                $contactParams
            ),
            'daily_profile_views' => self::rows(
                'SELECT DATE(created_at) AS label, COUNT(*) AS total, COUNT(DISTINCT session_id) AS secondary '
                . "FROM analytics_events WHERE brand_id=? AND event_name='provider_profile_viewed' AND is_excluded=0 AND {$eventDeviceSql} AND created_at BETWEEN ? AND ? "
                . 'GROUP BY DATE(created_at) ORDER BY label',
                $eventParams
            ),
            'pages' => self::humanisePages(self::rows(
                'SELECT route AS label, COUNT(*) AS total, COUNT(DISTINCT session_id) AS secondary '
                . "FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND created_at BETWEEN ? AND ? GROUP BY route ORDER BY total DESC LIMIT 25",
                $pageParams
            )),
            'sources' => self::rows(
                "SELECT COALESCE(NULLIF(referrer_source,''),'direct') AS label, COUNT(DISTINCT session_id) AS total, COUNT(*) AS secondary "
                . "FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND created_at BETWEEN ? AND ? GROUP BY referrer_source ORDER BY total DESC LIMIT 20",
                $pageParams
            ),
            'devices' => self::rows(
                "SELECT COALESCE(NULLIF(device_type,''),'unknown') AS label, COUNT(DISTINCT session_id) AS total, COUNT(*) AS secondary "
                . "FROM page_views WHERE brand_id=? AND {$pageDeviceSql} AND created_at BETWEEN ? AND ? GROUP BY device_type ORDER BY total DESC",
                $pageParams
            ),
            'services' => self::rows(
                "SELECT COALESCE(bpc.name, sc.name, 'Any service') AS label, COUNT(*) AS total, "
                . 'SUM(ps.result_count=0) AS secondary FROM provider_searches ps '
                . $searchDeviceSql
                . ' LEFT JOIN brand_provider_categories bpc ON bpc.id=ps.category_id AND bpc.brand_id=ps.brand_id '
                . 'LEFT JOIN service_categories sc ON sc.id=ps.category_id '
                . 'LEFT JOIN towns t ON t.id=ps.town_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.created_at BETWEEN ? AND ?{$locationSql} "
                . 'GROUP BY COALESCE(bpc.name, sc.name, \'Any service\') ORDER BY total DESC LIMIT 25',
                $searchParams
            ),
            'locations' => self::rows(
                "SELECT COALESCE(t.name, NULLIF(ps.postcode,''), 'Location not supplied') AS label, COUNT(*) AS total, SUM(ps.result_count=0) AS secondary "
                . 'FROM provider_searches ps '
                . $searchDeviceSql
                . ' LEFT JOIN towns t ON t.id=ps.town_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.created_at BETWEEN ? AND ?{$locationSql} "
                . "GROUP BY COALESCE(t.name, NULLIF(ps.postcode,''), 'Location not supplied') ORDER BY total DESC LIMIT 25",
                $searchParams
            ),
            'coverage_gaps' => Database::select(
                "SELECT ps.town_id,ps.category_id,COALESCE(t.name,NULLIF(ps.postcode,''),'Location not supplied') AS location_name,"
                . "COALESCE(st.abbreviation,'') AS state_abbr,COALESCE(bpc.name,sc.name,'Any service') AS service_name,"
                . 'COUNT(*) AS searches,MAX(ps.created_at) AS last_searched '
                . 'FROM provider_searches ps '
                . $searchDeviceSql
                . ' LEFT JOIN towns t ON t.id=ps.town_id LEFT JOIN states st ON st.id=COALESCE(t.state_id,ps.state_id) '
                . 'LEFT JOIN brand_provider_categories bpc ON bpc.id=ps.category_id AND bpc.brand_id=ps.brand_id '
                . 'LEFT JOIN service_categories sc ON sc.id=ps.category_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.result_count=0 AND ps.created_at BETWEEN ? AND ?{$locationSql} "
                . "GROUP BY ps.town_id,ps.category_id,COALESCE(t.name,NULLIF(ps.postcode,''),'Location not supplied'),COALESCE(st.abbreviation,''),COALESCE(bpc.name,sc.name,'Any service') "
                . 'ORDER BY searches DESC,last_searched DESC LIMIT 50',
                $searchParams
            ),
            'actions' => self::rows(
                'SELECT pca.action_type AS label, COUNT(*) AS total, COUNT(DISTINCT pca.session_id) AS secondary '
                . 'FROM provider_contact_actions pca '
                . $contactDeviceSql
                . ' WHERE pca.brand_id=? AND pca.is_excluded=0 AND pca.created_at BETWEEN ? AND ? '
                . 'GROUP BY pca.action_type ORDER BY total DESC',
                $contactParams
            ),
            'providers' => self::providerInterest($brandId, $start, $end, $location, $device),
            'filters' => [
                'location' => $location,
                'device' => $device,
            ],
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private static function providerInterest(
        int $brandId,
        string $start,
        string $end,
        ?string $location,
        ?string $device
    ): array {
        $searchDeviceSql = self::searchDeviceJoinSql($device);
        $contactDeviceSql = self::contactDeviceJoinSql($device);
        $locationSql = self::locationSql($location);
        $eventDeviceSql = self::eventDeviceSql($device);

        $searchParams = self::searchParams($brandId, $start, $end, $device, $location);
        $eventParams = self::eventParams($brandId, $start, $end, $device);
        $contactParams = self::contactParams($brandId, $start, $end, $device);

        return Database::select(
            'SELECT p.id AS provider_id, COALESCE(NULLIF(pbl.display_name,\'\'),p.business_name) AS label, '
            . 'SUM(x.impressions) AS impressions, SUM(x.profile_views) AS profile_views, SUM(x.contacts) AS contacts '
            . 'FROM ('
            . 'SELECT r.provider_id, COUNT(*) AS impressions, 0 AS profile_views, 0 AS contacts '
            . 'FROM provider_search_results r JOIN provider_searches ps ON ps.id=r.search_id '
            . $searchDeviceSql
            . ' LEFT JOIN towns t ON t.id=ps.town_id '
            . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND ps.created_at BETWEEN ? AND ?{$locationSql} GROUP BY r.provider_id "
            . 'UNION ALL SELECT provider_id, 0, COUNT(*), 0 FROM analytics_events '
            . "WHERE brand_id=? AND event_name='provider_profile_viewed' AND is_excluded=0 AND {$eventDeviceSql} AND created_at BETWEEN ? AND ? GROUP BY provider_id "
            . 'UNION ALL SELECT pca.provider_id, 0, 0, COUNT(*) FROM provider_contact_actions pca '
            . $contactDeviceSql
            . ' WHERE pca.brand_id=? AND pca.is_excluded=0 AND pca.created_at BETWEEN ? AND ? GROUP BY pca.provider_id'
            . ') x JOIN providers p ON p.id=x.provider_id '
            . 'JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=? '
            . 'GROUP BY p.id, pbl.display_name, p.business_name '
            . 'ORDER BY contacts DESC, profile_views DESC, impressions DESC LIMIT 50',
            array_merge($searchParams, $eventParams, $contactParams, [$brandId])
        );
    }

    private static function normaliseLocation(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $location = trim($value);
        if ($location === '' || mb_strlen($location) > 120) {
            return null;
        }

        return $location;
    }

    private static function normaliseDevice(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $device = strtolower(trim($value));
        if (!in_array($device, self::GENUINE_DEVICES, true)) {
            return null;
        }

        return $device;
    }

    private static function pageDeviceSql(?string $device): string
    {
        if ($device !== null) {
            return 'device_type = ?';
        }

        return "device_type NOT IN ('bot','unknown')";
    }

    private static function eventDeviceSql(?string $device): string
    {
        if ($device !== null) {
            return 'device_type = ?';
        }

        // Profile events keep the historical is_excluded=0 scope when unfiltered.
        return '1=1';
    }

    private static function searchDeviceJoinSql(?string $device): string
    {
        if ($device === null) {
            return '';
        }

        return ' INNER JOIN tracking_sessions ts_device ON ts_device.id=ps.session_id AND ts_device.device_type=? ';
    }

    private static function contactDeviceJoinSql(?string $device): string
    {
        if ($device === null) {
            return '';
        }

        return ' INNER JOIN tracking_sessions ts_device ON ts_device.id=pca.session_id AND ts_device.device_type=? ';
    }

    private static function locationSql(?string $location): string
    {
        if ($location === null) {
            return '';
        }

        return ' AND (t.name=? OR ps.postcode=?) ';
    }

    /**
     * @return array<int,mixed>
     */
    private static function pageParams(int $brandId, string $start, string $end, ?string $device): array
    {
        // SQL shape: brand_id=? [AND device_type=?] AND created_at BETWEEN ? AND ?
        if ($device !== null) {
            return [$brandId, $device, $start, $end];
        }

        return [$brandId, $start, $end];
    }

    /**
     * @return array<int,mixed>
     */
    private static function eventParams(int $brandId, string $start, string $end, ?string $device): array
    {
        return self::pageParams($brandId, $start, $end, $device);
    }

    /**
     * @return array<int,mixed>
     */
    private static function searchParams(
        int $brandId,
        string $start,
        string $end,
        ?string $device,
        ?string $location
    ): array {
        // JOIN bind (device) comes first, then WHERE brand/start/end, then location.
        $params = [];
        if ($device !== null) {
            $params[] = $device;
        }
        $params[] = $brandId;
        $params[] = $start;
        $params[] = $end;
        if ($location !== null) {
            $params[] = $location;
            $params[] = $location;
        }

        return $params;
    }

    /**
     * @return array<int,mixed>
     */
    private static function contactParams(int $brandId, string $start, string $end, ?string $device): array
    {
        return self::searchParams($brandId, $start, $end, $device, null);
    }

    /** @param array<int,mixed> $params */
    private static function count(string $sql, array $params): int
    {
        return (int) Database::scalar($sql, $params);
    }

    /** @param array<int,mixed> $params @return array<int,array<string,mixed>> */
    private static function rows(string $sql, array $params): array
    {
        return Database::select($sql, $params);
    }

    /** @param array<int,array<string,mixed>> $rows @return array<int,array<string,mixed>> */
    private static function humanisePages(array $rows): array
    {
        $exact = [
            '/' => 'Home page',
            '/providers' => 'Find local services',
            '/providers/search' => 'Provider search results',
            '/find' => 'Provider search results',
            '/services' => 'Services directory',
            '/places-to-stay' => 'Places to stay',
            '/stays' => 'Places to stay',
            '/contact' => 'Contact us',
            '/about' => 'About the platform',
            '/register-request' => 'Request help',
            '/request-assistance' => 'Request help',
            '/for-providers' => 'Information for providers',
            '/for-providers/register' => 'Provider registration',
            '/how-it-works' => 'How it works',
            '/faqs' => 'Frequently asked questions',
            '/privacy' => 'Privacy policy',
            '/terms' => 'Terms of use',
            '/disclaimer' => 'Important disclaimer',
        ];
        foreach ($rows as &$row) {
            $route = (string) ($row['label'] ?? '/');
            $friendly = $exact[$route] ?? null;
            if ($friendly === null) {
                $friendly = match (true) {
                    str_starts_with($route, '/providers/'), str_starts_with($route, '/business/') => 'Provider: ' . self::routeName($route),
                    str_starts_with($route, '/services/'), str_starts_with($route, '/category/') => 'Service: ' . self::routeName($route),
                    str_starts_with($route, '/towns/') => 'Town: ' . self::routeName($route),
                    str_starts_with($route, '/regions/') => 'Region: ' . self::routeName($route),
                    str_starts_with($route, '/caravan-parks/'), str_starts_with($route, '/places-to-stay/') => 'Place to stay: ' . self::routeName($route),
                    str_starts_with($route, '/rules') => 'Rules and regulations',
                    str_starts_with($route, '/motorsport') => 'Motorsport information',
                    default => ucwords(str_replace(['-', '_'], ' ', trim($route, '/'))) ?: 'Home page',
                };
            }
            $row['route'] = $route;
            $row['label'] = $friendly;
        }
        unset($row);
        return $rows;
    }

    private static function routeName(string $route): string
    {
        $slug = basename(trim($route, '/'));
        return ucwords(str_replace(['-', '_'], ' ', rawurldecode($slug)));
    }
}
