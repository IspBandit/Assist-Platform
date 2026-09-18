<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Database;

/**
 * Brand-scoped, aggregate read model for the operator-facing website insights
 * dashboard. It never exposes anonymous visitor identity or raw event metadata.
 */
final class WebsiteInsightsService
{
    /** @return array<string,mixed> */
    public static function report(int $brandId, string $from, string $to): array
    {
        [$start, $end] = [$from . ' 00:00:00', $to . ' 23:59:59'];
        $window = [$brandId, $start, $end];
        $publicPages = PublicPageViewPolicy::sqlPredicate('pv.route');
        $eligible = TrafficQuality::eligibleSessionSql('ts');
        $cleanPageFrom = " FROM page_views pv JOIN tracking_sessions ts ON ts.id=pv.session_id WHERE pv.brand_id=? AND pv.device_type NOT IN ('bot','unknown') AND {$eligible} AND {$publicPages}";
        $cleanSearchFrom = " FROM provider_searches ps JOIN tracking_sessions ts ON ts.id=ps.session_id WHERE ps.brand_id=? AND ps.is_excluded=0 AND {$eligible}";
        $cleanAskFrom = " FROM assist_searches a LEFT JOIN tracking_sessions ts ON ts.id=a.session_id WHERE a.brand_id=? AND a.is_excluded=0 AND (ts.id IS NULL OR ({$eligible}))";
        $cleanEventFrom = " FROM analytics_events ae JOIN tracking_sessions ts ON ts.id=ae.session_id WHERE ae.brand_id=? AND ae.is_excluded=0 AND {$eligible}";
        $cleanContactFrom = " FROM provider_contact_actions pca JOIN tracking_sessions ts ON ts.id=pca.session_id WHERE pca.brand_id=? AND pca.is_excluded=0 AND {$eligible}";

        $views = self::count('SELECT COUNT(*)' . $cleanPageFrom . ' AND pv.created_at BETWEEN ? AND ?', $window);
        $visitors = self::count('SELECT COUNT(DISTINCT pv.session_id)' . $cleanPageFrom . ' AND pv.created_at BETWEEN ? AND ?', $window);
        $signedIn = self::count('SELECT COUNT(DISTINCT pv.user_id)' . $cleanPageFrom . ' AND pv.user_id IS NOT NULL AND pv.created_at BETWEEN ? AND ?', $window);
        $returningVisitors = self::count(
            'SELECT COUNT(DISTINCT pv.session_id)' . $cleanPageFrom
            . " AND pv.created_at BETWEEN ? AND ? AND EXISTS (SELECT 1 FROM page_views prior WHERE prior.brand_id=pv.brand_id AND prior.session_id=pv.session_id AND prior.device_type NOT IN ('bot','unknown') AND "
            . PublicPageViewPolicy::sqlPredicate('prior.route') . ' AND prior.created_at < ?)',
            [$brandId, $start, $end, $start]
        );
        $multiDayVisitors = self::count(
            'SELECT COUNT(*) FROM (SELECT pv.session_id' . $cleanPageFrom
            . ' AND pv.created_at BETWEEN ? AND ? GROUP BY pv.session_id HAVING COUNT(DISTINCT DATE(pv.created_at)) >= 2) multi_day',
            $window
        );
        $searches = self::count('SELECT COUNT(*)' . $cleanSearchFrom . ' AND ps.created_at BETWEEN ? AND ?', $window);
        $noResults = self::count('SELECT COUNT(*)' . $cleanSearchFrom . ' AND ps.result_count=0 AND ps.created_at BETWEEN ? AND ?', $window);
        $exactMisses = self::count('SELECT COUNT(*)' . $cleanSearchFrom . ' AND ps.exact_match_count=0 AND ps.created_at BETWEEN ? AND ?', $window);
        $rescuedSearches = self::count('SELECT COUNT(*)' . $cleanSearchFrom . ' AND ps.exact_match_count=0 AND ps.result_count>0 AND ps.used_nearby_fallback=1 AND ps.created_at BETWEEN ? AND ?', $window);
        $askSearches = self::count('SELECT COUNT(*)' . $cleanAskFrom . ' AND a.created_at BETWEEN ? AND ?', $window);
        $askNoResults = self::count('SELECT COUNT(*)' . $cleanAskFrom . ' AND a.local_result_count=0 AND a.external_result_count=0 AND a.created_at BETWEEN ? AND ?', $window);
        $staySearches = self::count('SELECT COUNT(*)' . $cleanEventFrom . " AND ae.event_name IN ('stay_search_completed','no_stay_found') AND ae.created_at BETWEEN ? AND ?", $window);
        $stayNoResults = self::count('SELECT COUNT(*)' . $cleanEventFrom . " AND ae.event_name='no_stay_found' AND ae.created_at BETWEEN ? AND ?", $window);
        $profileViews = self::count('SELECT COUNT(*)' . $cleanEventFrom . " AND ae.event_name='provider_profile_viewed' AND ae.created_at BETWEEN ? AND ?", $window);
        $contacts = self::count('SELECT COUNT(*)' . $cleanContactFrom . ' AND pca.created_at BETWEEN ? AND ?', $window);
        $confirmed = self::count("SELECT COUNT(*) FROM service_outcomes WHERE brand_id=? AND is_excluded=0 AND confidence IN ('customer_reported','both_confirmed','admin_verified') AND created_at BETWEEN ? AND ?", $window);
        $lastPageView = Database::scalar('SELECT MAX(pv.created_at)' . $cleanPageFrom, [$brandId]);
        $lastDemandEvent = Database::scalar('SELECT MAX(created_at) FROM analytics_events WHERE brand_id=? AND is_excluded=0', [$brandId]);

        return [
            'summary' => [
                'page_views' => $views,
                'visitors' => $visitors,
                'new_visitors' => max(0, $visitors - $returningVisitors),
                'returning_visitors' => $returningVisitors,
                'returning_visitor_rate' => ReportingService::rate($returningVisitors, $visitors),
                'multi_day_visitors' => $multiDayVisitors,
                'signed_in_visitors' => $signedIn,
                'pages_per_visitor' => $visitors > 0 ? round($views / $visitors, 1) : null,
                'searches' => $searches,
                'no_results' => $noResults,
                'exact_misses' => $exactMisses,
                'rescued_searches' => $rescuedSearches,
                'ask_searches' => $askSearches,
                'ask_no_results' => $askNoResults,
                'ask_success_rate' => ReportingService::rate(max(0, $askSearches - $askNoResults), $askSearches),
                'stay_searches' => $staySearches,
                'stay_no_results' => $stayNoResults,
                'stay_success_rate' => ReportingService::rate(max(0, $staySearches - $stayNoResults), $staySearches),
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
                'SELECT DATE(pv.created_at) AS label, COUNT(*) AS total, COUNT(DISTINCT pv.session_id) AS secondary '
                . $cleanPageFrom . ' AND pv.created_at BETWEEN ? AND ? GROUP BY DATE(pv.created_at) ORDER BY label',
                $window
            ),
            'pages' => self::humanisePages(self::rows(
                'SELECT pv.route AS label, COUNT(*) AS total, COUNT(DISTINCT pv.session_id) AS secondary '
                . $cleanPageFrom . ' AND pv.created_at BETWEEN ? AND ? GROUP BY pv.route ORDER BY total DESC LIMIT 25',
                $window
            )),
            'sources' => self::rows(
                "SELECT COALESCE(NULLIF(pv.referrer_source,''),'direct') AS label, COUNT(DISTINCT pv.session_id) AS total, COUNT(*) AS secondary "
                . $cleanPageFrom . ' AND pv.created_at BETWEEN ? AND ? GROUP BY pv.referrer_source ORDER BY total DESC LIMIT 20',
                $window
            ),
            'devices' => self::rows(
                "SELECT COALESCE(NULLIF(pv.device_type,''),'unknown') AS label, COUNT(DISTINCT pv.session_id) AS total, COUNT(*) AS secondary "
                . $cleanPageFrom . ' AND pv.created_at BETWEEN ? AND ? GROUP BY pv.device_type ORDER BY total DESC',
                $window
            ),
            'services' => self::rows(
                "SELECT COALESCE(bpc.name, sc.name, 'Any service') AS label, COUNT(*) AS total, "
                . 'SUM(ps.result_count=0) AS secondary FROM provider_searches ps '
                . 'JOIN tracking_sessions ts ON ts.id=ps.session_id '
                . 'LEFT JOIN brand_provider_categories bpc ON bpc.id=ps.category_id AND bpc.brand_id=ps.brand_id '
                . 'LEFT JOIN service_categories sc ON sc.id=ps.category_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND {$eligible} AND ps.created_at BETWEEN ? AND ? "
                . 'GROUP BY COALESCE(bpc.name, sc.name, \'Any service\') ORDER BY total DESC LIMIT 25',
                $window
            ),
            'locations' => self::rows(
                "SELECT COALESCE(t.name, NULLIF(ps.postcode,''), 'Location not supplied') AS label, COUNT(*) AS total, SUM(ps.result_count=0) AS secondary "
                . 'FROM provider_searches ps JOIN tracking_sessions ts ON ts.id=ps.session_id LEFT JOIN towns t ON t.id=ps.town_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND {$eligible} AND ps.created_at BETWEEN ? AND ? "
                . "GROUP BY COALESCE(t.name, NULLIF(ps.postcode,''), 'Location not supplied') ORDER BY total DESC LIMIT 25",
                $window
            ),
            'coverage_gaps' => Database::select(
                "SELECT ps.town_id,ps.category_id,COALESCE(t.name,NULLIF(ps.postcode,''),'Location not supplied') AS location_name,"
                . "COALESCE(st.abbreviation,'') AS state_abbr,COALESCE(bpc.name,sc.name,'Any service') AS service_name,"
                . 'COUNT(*) AS searches,SUM(ps.result_count>0 AND ps.used_nearby_fallback=1) AS rescued_searches,MAX(ps.created_at) AS last_searched '
                . 'FROM provider_searches ps JOIN tracking_sessions ts ON ts.id=ps.session_id LEFT JOIN towns t ON t.id=ps.town_id LEFT JOIN states st ON st.id=COALESCE(t.state_id,ps.state_id) '
                . 'LEFT JOIN brand_provider_categories bpc ON bpc.id=ps.category_id AND bpc.brand_id=ps.brand_id '
                . 'LEFT JOIN service_categories sc ON sc.id=ps.category_id '
                . "WHERE ps.brand_id=? AND ps.is_excluded=0 AND {$eligible} AND ps.exact_match_count=0 AND ps.created_at BETWEEN ? AND ? "
                . "GROUP BY ps.town_id,ps.category_id,COALESCE(t.name,NULLIF(ps.postcode,''),'Location not supplied'),COALESCE(st.abbreviation,''),COALESCE(bpc.name,sc.name,'Any service') "
                . 'ORDER BY searches DESC,last_searched DESC LIMIT 50',
                $window
            ),
            'actions' => self::rows(
                'SELECT pca.action_type AS label, COUNT(*) AS total, COUNT(DISTINCT pca.session_id) AS secondary '
                . $cleanContactFrom . ' AND pca.created_at BETWEEN ? AND ? '
                . 'GROUP BY pca.action_type ORDER BY total DESC',
                $window
            ),
            'providers' => self::providerInterest($brandId, $start, $end),
            // Monetisation shortlists: named entities with progressive use evidence.
            'providers_used' => self::providersUsed($brandId, $start, $end),
            'providers_contacted' => self::providersContacted($brandId, $start, $end),
            'stays_engaged' => self::staysEngaged($brandId, $start, $end),
        ];
    }

    /** @return array<int,array<string,mixed>> */
    private static function providerInterest(int $brandId, string $start, string $end): array
    {
        $eligible = TrafficQuality::eligibleSessionSql('ts');
        return Database::select(
            'SELECT p.id AS provider_id, COALESCE(NULLIF(pbl.display_name,\'\'),p.business_name) AS label, '
            . 'SUM(x.impressions) AS impressions, SUM(x.profile_views) AS profile_views, SUM(x.contacts) AS contacts, '
            . 'SUM(x.confirmed_uses) AS confirmed_uses, '
            . 'ROUND(100 * SUM(x.profile_views) / NULLIF(SUM(x.impressions),0),1) AS impression_to_profile_rate, '
            . 'ROUND(100 * SUM(x.contacts) / NULLIF(SUM(x.profile_views),0),1) AS profile_to_contact_rate '
            . 'FROM ('
            . 'SELECT r.provider_id, COUNT(*) AS impressions, 0 AS profile_views, 0 AS contacts, 0 AS confirmed_uses '
            . 'FROM provider_search_results r JOIN provider_searches s ON s.id=r.search_id JOIN tracking_sessions ts ON ts.id=s.session_id '
            . "WHERE s.brand_id=? AND s.is_excluded=0 AND {$eligible} AND s.created_at BETWEEN ? AND ? GROUP BY r.provider_id "
            . 'UNION ALL SELECT ae.provider_id, 0, COUNT(*), 0, 0 FROM analytics_events ae JOIN tracking_sessions ts ON ts.id=ae.session_id '
            . "WHERE ae.brand_id=? AND ae.event_name='provider_profile_viewed' AND ae.is_excluded=0 AND {$eligible} AND ae.created_at BETWEEN ? AND ? GROUP BY ae.provider_id "
            . 'UNION ALL SELECT pca.provider_id, 0, 0, COUNT(*), 0 FROM provider_contact_actions pca JOIN tracking_sessions ts ON ts.id=pca.session_id '
            . "WHERE pca.brand_id=? AND pca.is_excluded=0 AND {$eligible} AND pca.created_at BETWEEN ? AND ? GROUP BY pca.provider_id "
            . 'UNION ALL SELECT so.provider_id, 0, 0, 0, COUNT(*) FROM service_outcomes so '
            . "WHERE so.brand_id=? AND so.is_excluded=0 AND so.confidence IN ('customer_reported','both_confirmed','admin_verified') "
            . 'AND so.created_at BETWEEN ? AND ? GROUP BY so.provider_id'
            . ') x JOIN providers p ON p.id=x.provider_id '
            . 'JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=? '
            . 'GROUP BY p.id, pbl.display_name, p.business_name '
            . 'ORDER BY confirmed_uses DESC, contacts DESC, profile_views DESC, impressions DESC LIMIT 50',
            [$brandId, $start, $end, $brandId, $start, $end, $brandId, $start, $end, $brandId, $start, $end, $brandId]
        );
    }

    /**
     * Named providers with customer/admin confirmed use — strongest monetisation evidence.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function providersUsed(int $brandId, string $start, string $end): array
    {
        return Database::select(
            'SELECT p.id AS provider_id, COALESCE(NULLIF(pbl.display_name,\'\'),p.business_name) AS label, '
            . 'COUNT(*) AS confirmed_uses, '
            . "SUM(so.confidence IN ('both_confirmed','admin_verified')) AS mutually_confirmed, "
            . 'COUNT(DISTINCT so.customer_id) AS distinct_customers, '
            . 'MAX(so.created_at) AS last_confirmed_at '
            . 'FROM service_outcomes so '
            . 'JOIN providers p ON p.id=so.provider_id '
            . 'JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=so.brand_id '
            . "WHERE so.brand_id=? AND so.is_excluded=0 "
            . "AND so.confidence IN ('customer_reported','both_confirmed','admin_verified') "
            . 'AND so.created_at BETWEEN ? AND ? '
            . 'GROUP BY p.id, pbl.display_name, p.business_name '
            . 'ORDER BY confirmed_uses DESC, mutually_confirmed DESC, last_confirmed_at DESC LIMIT 50',
            [$brandId, $start, $end]
        );
    }

    /**
     * Named providers visitors contacted — strong intent shortlist before confirmed use.
     *
     * @return array<int,array<string,mixed>>
     */
    private static function providersContacted(int $brandId, string $start, string $end): array
    {
        $eligible = TrafficQuality::eligibleSessionSql('ts');
        return Database::select(
            'SELECT p.id AS provider_id, COALESCE(NULLIF(pbl.display_name,\'\'),p.business_name) AS label, '
            . 'COUNT(*) AS contacts, COUNT(DISTINCT pca.session_id) AS visitors, '
            . "SUM(pca.action_type='phone') AS phone, "
            . "SUM(pca.action_type IN ('email','message')) AS messages, "
            . "SUM(pca.action_type='website') AS website, "
            . "SUM(pca.action_type='directions') AS directions, "
            . "SUM(pca.action_type IN ('assistance_request','quote_request','booking_request')) AS requests, "
            . 'MAX(pca.created_at) AS last_contact_at '
            . 'FROM provider_contact_actions pca '
            . 'JOIN tracking_sessions ts ON ts.id=pca.session_id '
            . 'JOIN providers p ON p.id=pca.provider_id '
            . 'JOIN provider_brand_listings pbl ON pbl.provider_id=p.id AND pbl.brand_id=pca.brand_id '
            . "WHERE pca.brand_id=? AND pca.is_excluded=0 AND {$eligible} AND pca.created_at BETWEEN ? AND ? "
            . 'GROUP BY p.id, pbl.display_name, p.business_name '
            . 'ORDER BY contacts DESC, requests DESC, phone DESC, last_contact_at DESC LIMIT 50',
            [$brandId, $start, $end]
        );
    }

    /**
     * Named places to stay with measurable engagement (contacts, assistance, page views).
     *
     * @return array<int,array<string,mixed>>
     */
    private static function staysEngaged(int $brandId, string $start, string $end): array
    {
        // Stay listings are VanAssist-scoped; other brands return an empty shortlist.
        if ($brandId !== 1) {
            return [];
        }

        $eligible = TrafficQuality::eligibleSessionSql('ts');
        $publicPages = PublicPageViewPolicy::sqlPredicate('pv.route');
        $views = Database::select(
            'SELECT cp.id AS park_id, cp.name AS label, cp.slug, '
            . 'COUNT(*) AS page_views, COUNT(DISTINCT pv.session_id) AS visitors '
            . 'FROM page_views pv '
            . 'JOIN tracking_sessions ts ON ts.id=pv.session_id '
            . "JOIN caravan_parks cp ON cp.slug = SUBSTRING_INDEX(TRIM(BOTH '/' FROM pv.route), '/', -1) "
            . 'AND cp.deleted_at IS NULL '
            . "WHERE pv.brand_id=? AND pv.device_type NOT IN ('bot','unknown') AND {$eligible} AND {$publicPages} "
            . "AND pv.route REGEXP '^/caravan-parks/[a-z0-9-]+$' "
            . 'AND pv.created_at BETWEEN ? AND ? '
            . 'GROUP BY cp.id, cp.name, cp.slug',
            [$brandId, $start, $end]
        );

        $requests = Database::select(
            'SELECT sr.park_id, COUNT(*) AS assistance_requests '
            . 'FROM service_requests sr '
            . 'WHERE sr.park_id IS NOT NULL AND sr.deleted_at IS NULL '
            . 'AND sr.created_at BETWEEN ? AND ? '
            . 'GROUP BY sr.park_id',
            [$start, $end]
        );
        $requestByPark = [];
        foreach ($requests as $row) {
            $requestByPark[(int) $row['park_id']] = (int) $row['assistance_requests'];
        }

        $contactByPark = [];
        if (Database::tableExists('stay_contact_actions')) {
            foreach (Database::select(
                'SELECT sca.park_id, COUNT(*) AS contacts, COUNT(DISTINCT sca.session_id) AS contact_visitors, '
                . "SUM(sca.action_type='phone') AS phone, "
                . "SUM(sca.action_type='website') AS website, "
                . "SUM(sca.action_type='booking') AS booking, "
                . "SUM(sca.action_type='directions') AS directions "
                . 'FROM stay_contact_actions sca '
                . 'LEFT JOIN tracking_sessions ts ON ts.id=sca.session_id '
                . "WHERE sca.brand_id=? AND sca.is_excluded=0 AND (ts.id IS NULL OR ({$eligible})) "
                . 'AND sca.created_at BETWEEN ? AND ? '
                . 'GROUP BY sca.park_id',
                [$brandId, $start, $end]
            ) as $row) {
                $contactByPark[(int) $row['park_id']] = [
                    'contacts' => (int) $row['contacts'],
                    'contact_visitors' => (int) $row['contact_visitors'],
                    'phone' => (int) $row['phone'],
                    'website' => (int) $row['website'],
                    'booking' => (int) $row['booking'],
                    'directions' => (int) $row['directions'],
                ];
            }
        }

        $byPark = [];
        $ensure = static function (int $parkId, string $label, string $slug) use (&$byPark, $requestByPark, $contactByPark): void {
            if (isset($byPark[$parkId])) {
                return;
            }
            $contacts = $contactByPark[$parkId] ?? [
                'contacts' => 0, 'contact_visitors' => 0, 'phone' => 0, 'website' => 0, 'booking' => 0, 'directions' => 0,
            ];
            $byPark[$parkId] = [
                'park_id' => $parkId,
                'label' => $label,
                'slug' => $slug,
                'page_views' => 0,
                'visitors' => 0,
                'assistance_requests' => $requestByPark[$parkId] ?? 0,
                'contacts' => $contacts['contacts'],
                'contact_visitors' => $contacts['contact_visitors'],
                'phone' => $contacts['phone'],
                'website' => $contacts['website'],
                'booking' => $contacts['booking'],
                'directions' => $contacts['directions'],
            ];
        };

        foreach ($views as $row) {
            $id = (int) $row['park_id'];
            $ensure($id, (string) $row['label'], (string) $row['slug']);
            $byPark[$id]['page_views'] = (int) $row['page_views'];
            $byPark[$id]['visitors'] = (int) $row['visitors'];
        }
        foreach ($requestByPark as $parkId => $count) {
            if (isset($byPark[$parkId])) {
                $byPark[$parkId]['assistance_requests'] = $count;
                continue;
            }
            $park = Database::selectOne(
                'SELECT id, name, slug FROM caravan_parks WHERE id=? AND deleted_at IS NULL',
                [$parkId]
            );
            if ($park === null) {
                continue;
            }
            $ensure($parkId, (string) $park['name'], (string) $park['slug']);
            $byPark[$parkId]['assistance_requests'] = $count;
        }
        foreach ($contactByPark as $parkId => $_) {
            if (isset($byPark[$parkId])) {
                continue;
            }
            $park = Database::selectOne(
                'SELECT id, name, slug FROM caravan_parks WHERE id=? AND deleted_at IS NULL',
                [$parkId]
            );
            if ($park === null) {
                continue;
            }
            $ensure($parkId, (string) $park['name'], (string) $park['slug']);
        }

        $rows = array_values($byPark);
        usort(
            $rows,
            static fn (array $a, array $b): int => ($b['contacts'] <=> $a['contacts'])
                ?: ($b['assistance_requests'] <=> $a['assistance_requests'])
                ?: ($b['page_views'] <=> $a['page_views'])
                ?: ($b['visitors'] <=> $a['visitors'])
        );

        return array_slice($rows, 0, 50);
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
            '/privacy-policy' => 'Privacy policy',
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
