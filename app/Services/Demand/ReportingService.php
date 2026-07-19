<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Core\Database;

/**
 * Read-side reporting for the demand funnel. Builds provider-scoped and
 * platform-scoped metric sets from the analytics tables, with date-range and
 * Australian financial-year support.
 *
 * Every figure is labelled estimated vs confirmed by the dashboards: clicks
 * and impressions are estimates of interest; "confirmed use" comes only from
 * service_outcomes with a customer/provider/admin confirmation.
 */
final class ReportingService
{
    /**
     * Resolve a named range to [fromDate, toDate, label] (inclusive dates).
     *
     * @return array{0:string,1:string,2:string}
     */
    public static function resolveRange(string $range, string $from = '', string $to = ''): array
    {
        $today = new \DateTimeImmutable('today');
        switch ($range) {
            case '7d':
                return [$today->modify('-6 days')->format('Y-m-d'), $today->format('Y-m-d'), 'Last 7 days'];
            case '90d':
                return [$today->modify('-89 days')->format('Y-m-d'), $today->format('Y-m-d'), 'Last 90 days'];
            case 'fy':
                [$s, $e] = self::financialYear(0);
                return [$s, $e, 'This financial year'];
            case 'pfy':
                [$s, $e] = self::financialYear(-1);
                return [$s, $e, 'Previous financial year'];
            case 'custom':
                $f = self::validDate($from) ?? $today->modify('-29 days')->format('Y-m-d');
                $t = self::validDate($to) ?? $today->format('Y-m-d');
                return [$f, $t, $f . ' to ' . $t];
            case '30d':
            default:
                return [$today->modify('-29 days')->format('Y-m-d'), $today->format('Y-m-d'), 'Last 30 days'];
        }
    }

    /** AU financial year (1 Jul – 30 Jun). $offset 0 = current, -1 = previous. */
    public static function financialYear(int $offset): array
    {
        $now = new \DateTimeImmutable('today');
        $startYear = (int) $now->format('Y') - ((int) $now->format('n') >= 7 ? 0 : 1) + $offset;
        return [sprintf('%d-07-01', $startYear), sprintf('%d-06-30', $startYear + 1)];
    }

    /** Bound a date to a full-day window for BETWEEN comparisons. */
    private static function bounds(string $from, string $to): array
    {
        return [$from . ' 00:00:00', $to . ' 23:59:59'];
    }

    /**
     * Full metric set for one provider over a date range.
     *
     * @return array<string,mixed>
     */
    public static function providerSummary(int $providerId, string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);

        $impressions = (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_search_results r JOIN provider_searches s ON s.id = r.search_id '
            . 'WHERE r.provider_id = ? AND s.is_excluded = 0 AND s.created_at BETWEEN ? AND ?',
            [$providerId, $start, $end]
        );
        $uniqueImpressions = (int) Database::scalar(
            'SELECT COUNT(DISTINCT s.session_id) FROM provider_search_results r JOIN provider_searches s ON s.id = r.search_id '
            . 'WHERE r.provider_id = ? AND s.is_excluded = 0 AND s.created_at BETWEEN ? AND ?',
            [$providerId, $start, $end]
        );

        $profileViews = (int) Database::scalar(
            "SELECT COUNT(*) FROM analytics_events WHERE event_name = 'provider_profile_viewed' "
            . 'AND provider_id = ? AND is_excluded = 0 AND created_at BETWEEN ? AND ?',
            [$providerId, $start, $end]
        );
        $uniqueProfileViews = (int) Database::scalar(
            "SELECT COUNT(DISTINCT session_id) FROM analytics_events WHERE event_name = 'provider_profile_viewed' "
            . 'AND provider_id = ? AND is_excluded = 0 AND created_at BETWEEN ? AND ?',
            [$providerId, $start, $end]
        );

        $clicks = ['phone' => 0, 'email' => 0, 'website' => 0, 'directions' => 0, 'message' => 0,
            'assistance_request' => 0, 'quote_request' => 0, 'booking_request' => 0];
        foreach (Database::select(
            'SELECT action_type, COUNT(*) c FROM provider_contact_actions '
            . 'WHERE provider_id = ? AND is_excluded = 0 AND created_at BETWEEN ? AND ? GROUP BY action_type',
            [$providerId, $start, $end]
        ) as $row) {
            $clicks[(string) $row['action_type']] = (int) $row['c'];
        }
        $contactActions = array_sum($clicks);

        // Outcome funnel (engagements created in range).
        $o = Database::selectOne(
            'SELECT '
            . 'COUNT(*) AS engagements, '
            . 'SUM(responded_at IS NOT NULL) AS responses, '
            . "SUM(status = 'quoted') AS quotes, "
            . 'SUM(selected_at IS NOT NULL) AS selections, '
            . 'SUM(booked_at IS NOT NULL) AS bookings, '
            . 'SUM(completed_at IS NOT NULL) AS completed, '
            . "SUM(status = 'cancelled') AS cancellations, "
            . "SUM(confidence IN ('customer_reported','both_confirmed','admin_verified')) AS customer_confirmed, "
            . "SUM(confidence = 'provider_reported') AS provider_confirmed, "
            . "SUM(confidence IN ('both_confirmed','admin_verified')) AS mutually_confirmed, "
            . 'SUM(is_repeat_provider = 1) AS repeat_uses '
            . 'FROM service_outcomes WHERE provider_id = ? AND is_excluded = 0 AND created_at BETWEEN ? AND ?',
            [$providerId, $start, $end]
        ) ?: [];

        $repeatCustomers = (int) Database::scalar(
            'SELECT COUNT(DISTINCT customer_id) FROM service_outcomes '
            . 'WHERE provider_id = ? AND is_repeat_provider = 1 AND customer_id IS NOT NULL AND created_at BETWEEN ? AND ?',
            [$providerId, $start, $end]
        );

        $rev = Database::selectOne(
            "SELECT COUNT(*) c, COALESCE(AVG(rating),0) avg_rating FROM provider_reviews "
            . "WHERE provider_id = ? AND status = 'published' AND created_at BETWEEN ? AND ?",
            [$providerId, $start, $end]
        ) ?: ['c' => 0, 'avg_rating' => 0];

        $topCategories = Database::select(
            'SELECT c.name, COUNT(*) c FROM service_outcomes o JOIN service_categories c ON c.id = o.category_id '
            . 'WHERE o.provider_id = ? AND o.created_at BETWEEN ? AND ? GROUP BY o.category_id ORDER BY c DESC LIMIT 5',
            [$providerId, $start, $end]
        );
        $topLocations = Database::select(
            'SELECT t.name, COUNT(*) c FROM service_outcomes o JOIN towns t ON t.id = o.town_id '
            . 'WHERE o.provider_id = ? AND o.created_at BETWEEN ? AND ? GROUP BY o.town_id ORDER BY c DESC LIMIT 5',
            [$providerId, $start, $end]
        );

        $requests = (int) ($o['engagements'] ?? 0);
        $responses = (int) ($o['responses'] ?? 0);

        return [
            'impressions'         => $impressions,
            'unique_impressions'  => $uniqueImpressions,
            'profile_views'       => $profileViews,
            'unique_profile_views' => $uniqueProfileViews,
            'clicks'              => $clicks,
            'contact_actions'     => $contactActions,
            'requests'            => $requests,
            'responses'           => $responses,
            'quotes'              => (int) ($o['quotes'] ?? 0),
            'selections'          => (int) ($o['selections'] ?? 0),
            'bookings'            => (int) ($o['bookings'] ?? 0),
            'completed'           => (int) ($o['completed'] ?? 0),
            'cancellations'       => (int) ($o['cancellations'] ?? 0),
            'customer_confirmed'  => (int) ($o['customer_confirmed'] ?? 0),
            'provider_confirmed'  => (int) ($o['provider_confirmed'] ?? 0),
            'mutually_confirmed'  => (int) ($o['mutually_confirmed'] ?? 0),
            'repeat_uses'         => (int) ($o['repeat_uses'] ?? 0),
            'repeat_customers'    => $repeatCustomers,
            'reviews'             => (int) $rev['c'],
            'avg_rating'          => round((float) $rev['avg_rating'], 1),
            'response_rate'       => self::rate($responses, $requests),
            'view_to_contact'     => self::rate($contactActions, $profileViews),
            'contact_to_confirmed' => self::rate((int) ($o['customer_confirmed'] ?? 0), $contactActions),
            'top_categories'      => $topCategories,
            'top_locations'       => $topLocations,
        ];
    }

    /** Percentage with low-sample suppression (null when denominator is tiny). */
    public static function rate(int $numerator, int $denominator): ?float
    {
        if ($denominator < 5) {
            return null;
        }
        return round($numerator / $denominator * 100, 1);
    }

    // =====================================================================
    // Platform-wide (admin) reporting
    // =====================================================================

    /** @return array<string,int> */
    public static function platformOverview(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);

        $needs = (int) Database::scalar(
            'SELECT COUNT(*) FROM service_requests WHERE is_demo = 0 AND is_spam = 0 AND deleted_at IS NULL AND created_at BETWEEN ? AND ?',
            [$start, $end]
        );
        $searches = (int) Database::scalar('SELECT COUNT(*) FROM provider_searches WHERE is_excluded = 0 AND created_at BETWEEN ? AND ?', [$start, $end]);
        $noResult = (int) Database::scalar('SELECT COUNT(*) FROM provider_searches WHERE is_excluded = 0 AND result_count = 0 AND created_at BETWEEN ? AND ?', [$start, $end]);
        $impressions = (int) Database::scalar(
            'SELECT COUNT(*) FROM provider_search_results r JOIN provider_searches s ON s.id = r.search_id WHERE s.is_excluded = 0 AND s.created_at BETWEEN ? AND ?',
            [$start, $end]
        );
        $profileViews = (int) Database::scalar("SELECT COUNT(*) FROM analytics_events WHERE event_name = 'provider_profile_viewed' AND is_excluded = 0 AND created_at BETWEEN ? AND ?", [$start, $end]);
        $contacts = (int) Database::scalar('SELECT COUNT(*) FROM provider_contact_actions WHERE is_excluded = 0 AND created_at BETWEEN ? AND ?', [$start, $end]);

        $o = Database::selectOne(
            'SELECT '
            . 'SUM(responded_at IS NOT NULL) AS responses, '
            . "SUM(status = 'quoted') AS quotes, "
            . 'SUM(selected_at IS NOT NULL) AS selections, '
            . 'SUM(booked_at IS NOT NULL) AS bookings, '
            . 'SUM(completed_at IS NOT NULL) AS completed, '
            . "SUM(status = 'cancelled') AS cancellations, "
            . "SUM(confidence IN ('customer_reported','both_confirmed','admin_verified')) AS customer_confirmed, "
            . "SUM(confidence = 'provider_reported') AS provider_confirmed, "
            . "SUM(confidence IN ('both_confirmed','admin_verified')) AS mutually_confirmed "
            . 'FROM service_outcomes WHERE is_excluded = 0 AND created_at BETWEEN ? AND ?',
            [$start, $end]
        ) ?: [];

        return [
            'needs' => $needs, 'searches' => $searches, 'no_result' => $noResult,
            'impressions' => $impressions, 'profile_views' => $profileViews, 'contacts' => $contacts,
            'responses' => (int) ($o['responses'] ?? 0), 'quotes' => (int) ($o['quotes'] ?? 0),
            'selections' => (int) ($o['selections'] ?? 0), 'bookings' => (int) ($o['bookings'] ?? 0),
            'completed' => (int) ($o['completed'] ?? 0), 'cancellations' => (int) ($o['cancellations'] ?? 0),
            'customer_confirmed' => (int) ($o['customer_confirmed'] ?? 0),
            'provider_confirmed' => (int) ($o['provider_confirmed'] ?? 0),
            'mutually_confirmed' => (int) ($o['mutually_confirmed'] ?? 0),
        ];
    }

    /**
     * Demand breakdown by a structured dimension.
     *
     * @return array<int,array{name:string,c:int}>
     */
    public static function needsBy(string $dimension, string $from, string $to, int $limit = 25): array
    {
        [$start, $end] = self::bounds($from, $to);
        $base = 'FROM service_requests sr WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND sr.created_at BETWEEN ? AND ?';

        switch ($dimension) {
            case 'category':
                $sql = 'SELECT c.name AS name, COUNT(*) AS c FROM service_requests sr JOIN service_categories c ON c.id = sr.primary_category_id '
                    . 'WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND sr.created_at BETWEEN ? AND ? GROUP BY sr.primary_category_id ORDER BY c DESC LIMIT ' . $limit;
                break;
            case 'town':
                $sql = 'SELECT t.name AS name, COUNT(*) AS c FROM service_requests sr JOIN towns t ON t.id = sr.town_id '
                    . 'WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND sr.created_at BETWEEN ? AND ? GROUP BY sr.town_id ORDER BY c DESC LIMIT ' . $limit;
                break;
            case 'region':
                $sql = 'SELECT r.name AS name, COUNT(*) AS c FROM service_requests sr JOIN regions r ON r.id = sr.region_id '
                    . 'WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND sr.created_at BETWEEN ? AND ? GROUP BY sr.region_id ORDER BY c DESC LIMIT ' . $limit;
                break;
            case 'state':
                $sql = 'SELECT st.name AS name, COUNT(*) AS c FROM service_requests sr JOIN states st ON st.id = sr.state_id '
                    . 'WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND sr.created_at BETWEEN ? AND ? GROUP BY sr.state_id ORDER BY c DESC LIMIT ' . $limit;
                break;
            case 'urgency':
                $sql = 'SELECT sr.urgency AS name, COUNT(*) AS c ' . $base . ' GROUP BY sr.urgency ORDER BY c DESC';
                break;
            case 'vehicle_type':
                $sql = 'SELECT sr.vehicle_type AS name, COUNT(*) AS c ' . $base . ' GROUP BY sr.vehicle_type ORDER BY c DESC';
                break;
            default:
                return [];
        }

        $rows = Database::select($sql, [$start, $end]);
        return array_map(static fn ($r) => ['name' => (string) ($r['name'] ?? '—'), 'c' => (int) $r['c']], $rows);
    }

    /**
     * The demand-to-outcome funnel with conversion rates between stages.
     *
     * @return array<int,array{label:string,count:int,rate:?float}>
     */
    public static function funnel(string $from, string $to): array
    {
        $o = self::platformOverview($from, $to);
        $stages = [
            ['Searches', $o['searches']],
            ['Profile views', $o['profile_views']],
            ['Contact actions', $o['contacts']],
            ['Provider responses', $o['responses']],
            ['Quotes', $o['quotes']],
            ['Selections', $o['selections']],
            ['Bookings', $o['bookings']],
            ['Completed', $o['completed']],
            ['Confirmed use', $o['customer_confirmed']],
        ];
        $out = [];
        $prev = null;
        foreach ($stages as [$label, $count]) {
            $out[] = ['label' => $label, 'count' => $count, 'rate' => $prev === null ? null : self::rate($count, $prev)];
            $prev = $count;
        }
        return $out;
    }

    /**
     * Per-provider performance for the admin usage dashboard.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function providerPerformance(string $from, string $to, int $limit = 100): array
    {
        [$start, $end] = self::bounds($from, $to);

        $impressions = self::keyedCounts(
            'SELECT r.provider_id AS k, COUNT(*) AS c FROM provider_search_results r JOIN provider_searches s ON s.id = r.search_id '
            . 'WHERE s.is_excluded = 0 AND s.created_at BETWEEN ? AND ? GROUP BY r.provider_id',
            [$start, $end]
        );
        $views = self::keyedCounts(
            "SELECT provider_id AS k, COUNT(*) AS c FROM analytics_events WHERE event_name = 'provider_profile_viewed' AND is_excluded = 0 AND provider_id IS NOT NULL AND created_at BETWEEN ? AND ? GROUP BY provider_id",
            [$start, $end]
        );
        $contacts = self::keyedCounts(
            'SELECT provider_id AS k, COUNT(*) AS c FROM provider_contact_actions WHERE is_excluded = 0 AND created_at BETWEEN ? AND ? GROUP BY provider_id',
            [$start, $end]
        );

        $outcomes = Database::select(
            'SELECT provider_id AS k, COUNT(*) AS engagements, '
            . "SUM(confidence IN ('customer_reported','both_confirmed','admin_verified')) AS customer_confirmed, "
            . "SUM(confidence = 'provider_reported') AS provider_confirmed, "
            . "SUM(confidence IN ('both_confirmed','admin_verified')) AS mutually_confirmed, "
            . "SUM(status = 'cancelled') AS cancellations "
            . 'FROM service_outcomes WHERE is_excluded = 0 AND created_at BETWEEN ? AND ? GROUP BY provider_id',
            [$start, $end]
        );
        $outByProvider = [];
        foreach ($outcomes as $r) {
            $outByProvider[(int) $r['k']] = $r;
        }

        $ids = array_unique(array_merge(array_keys($impressions), array_keys($views), array_keys($contacts), array_keys($outByProvider)));
        if ($ids === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $names = [];
        foreach (Database::select("SELECT id, business_name, is_verified FROM providers WHERE id IN ({$placeholders})", array_values($ids)) as $p) {
            $names[(int) $p['id']] = $p;
        }

        $rows = [];
        foreach ($ids as $id) {
            $o = $outByProvider[$id] ?? [];
            $rows[] = [
                'provider_id'        => $id,
                'business_name'      => (string) ($names[$id]['business_name'] ?? ('#' . $id)),
                'is_verified'        => (int) ($names[$id]['is_verified'] ?? 0),
                'impressions'        => $impressions[$id] ?? 0,
                'profile_views'      => $views[$id] ?? 0,
                'contacts'           => $contacts[$id] ?? 0,
                'engagements'        => (int) ($o['engagements'] ?? 0),
                'customer_confirmed' => (int) ($o['customer_confirmed'] ?? 0),
                'provider_confirmed' => (int) ($o['provider_confirmed'] ?? 0),
                'mutually_confirmed' => (int) ($o['mutually_confirmed'] ?? 0),
                'cancellations'      => (int) ($o['cancellations'] ?? 0),
            ];
        }
        usort($rows, static fn ($a, $b) => ($b['customer_confirmed'] <=> $a['customer_confirmed']) ?: ($b['contacts'] <=> $a['contacts']));
        return array_slice($rows, 0, $limit);
    }

    /** @return array<string,mixed> */
    public static function coverageGaps(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);

        $zeroResult = Database::select(
            'SELECT COALESCE(t.name, "—") AS town, COALESCE(c.name, "Any") AS category, COUNT(*) AS c '
            . 'FROM provider_searches s LEFT JOIN towns t ON t.id = s.town_id LEFT JOIN service_categories c ON c.id = s.category_id '
            . 'WHERE s.is_excluded = 0 AND s.result_count = 0 AND s.created_at BETWEEN ? AND ? '
            . 'GROUP BY s.town_id, s.category_id ORDER BY c DESC LIMIT 50',
            [$start, $end]
        );

        $gapReasons = Database::select(
            'SELECT reason, COUNT(*) c FROM demand_gap_feedback WHERE created_at BETWEEN ? AND ? GROUP BY reason ORDER BY c DESC',
            [$start, $end]
        );
        $gapTowns = Database::select(
            'SELECT t.name, COUNT(*) c FROM demand_gap_feedback g JOIN towns t ON t.id = g.town_id WHERE g.created_at BETWEEN ? AND ? GROUP BY g.town_id ORDER BY c DESC LIMIT 25',
            [$start, $end]
        );

        // Shown often but rarely contacted, and contacted but rarely confirmed.
        $perf = self::providerPerformance($from, $to, 500);
        $shownNotContacted = array_values(array_filter($perf, static fn ($r) => $r['impressions'] >= 20 && $r['contacts'] === 0));
        usort($shownNotContacted, static fn ($a, $b) => $b['impressions'] <=> $a['impressions']);
        $contactedNotConfirmed = array_values(array_filter($perf, static fn ($r) => $r['contacts'] >= 5 && $r['customer_confirmed'] === 0));
        usort($contactedNotConfirmed, static fn ($a, $b) => $b['contacts'] <=> $a['contacts']);

        return [
            'zero_result' => $zeroResult,
            'gap_reasons' => $gapReasons,
            'gap_towns'   => $gapTowns,
            'shown_not_contacted' => array_slice($shownNotContacted, 0, 25),
            'contacted_not_confirmed' => array_slice($contactedNotConfirmed, 0, 25),
        ];
    }

    /**
     * Aggregated demand + provider locations for the admin map (town-level, no
     * street addresses). Confirmed use counts overlaid per town.
     *
     * @return array<string,mixed>
     */
    public static function demandMap(string $from, string $to): array
    {
        [$start, $end] = self::bounds($from, $to);

        $demand = Database::select(
            'SELECT t.id, t.name, t.latitude AS lat, t.longitude AS lng, COUNT(*) AS needs '
            . 'FROM service_requests sr JOIN towns t ON t.id = sr.town_id '
            . 'WHERE sr.is_demo = 0 AND sr.is_spam = 0 AND sr.deleted_at IS NULL AND t.latitude IS NOT NULL '
            . 'AND sr.created_at BETWEEN ? AND ? GROUP BY t.id, t.name, t.latitude, t.longitude ORDER BY needs DESC LIMIT 500',
            [$start, $end]
        );

        // Separate series so unclaimed metros don't crowd verified/claimed towns
        // out of a shared LIMIT. No hard cap — admin map should show national coverage.
        $unclaimed = Database::select(
            'SELECT t.id, t.name, t.latitude AS lat, t.longitude AS lng, COUNT(*) AS providers '
            . 'FROM providers p JOIN towns t ON t.id = p.base_town_id '
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL AND p.is_unclaimed = 1 "
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL '
            . 'GROUP BY t.id, t.name, t.latitude, t.longitude ORDER BY providers DESC'
        );

        $verified = Database::select(
            'SELECT t.id, t.name, t.latitude AS lat, t.longitude AS lng, COUNT(*) AS providers '
            . 'FROM providers p JOIN towns t ON t.id = p.base_town_id '
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL AND p.is_unclaimed = 0 AND p.is_verified = 1 "
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL '
            . 'GROUP BY t.id, t.name, t.latitude, t.longitude ORDER BY providers DESC'
        );

        $claimed = Database::select(
            'SELECT t.id, t.name, t.latitude AS lat, t.longitude AS lng, COUNT(*) AS providers '
            . 'FROM providers p JOIN towns t ON t.id = p.base_town_id '
            . "WHERE p.status = 'active' AND p.deleted_at IS NULL AND p.is_unclaimed = 0 AND p.is_verified = 0 "
            . 'AND t.latitude IS NOT NULL AND t.longitude IS NOT NULL '
            . 'GROUP BY t.id, t.name, t.latitude, t.longitude ORDER BY providers DESC'
        );

        return [
            'demand'             => $demand,
            'providers'          => $claimed,
            'providersUnclaimed' => $unclaimed,
            'providersVerified'  => $verified,
        ];
    }

    /**
     * @param array<int,mixed> $params
     * @return array<int,int> keyed by the first selected column (k)
     */
    private static function keyedCounts(string $sql, array $params): array
    {
        $out = [];
        foreach (Database::select($sql, $params) as $row) {
            $out[(int) $row['k']] = (int) $row['c'];
        }
        return $out;
    }

    private static function validDate(string $d): ?string
    {
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $d);
        return $dt && $dt->format('Y-m-d') === $d ? $d : null;
    }
}
