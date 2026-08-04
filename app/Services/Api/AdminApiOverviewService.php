<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Config;
use App\Core\Database;
use App\Core\Request;
use App\Services\Demand\ReportingService;
use App\Services\Demand\WebsiteInsightsService;
use Throwable;

/**
 * Operational overview rollup for Assist RIC everyday management.
 * Reuses existing reporting and queue tables — no parallel analytics.
 */
final class AdminApiOverviewService
{
    /** @return array<string,mixed> */
    public function overview(Request $request): array
    {
        [$from, $to, $label] = $this->dateRange($request);
        $brand = AdminApiBrandScope::brand();
        $brandId = $brand->databaseId();

        $release = trim((string) Config::get('app.release', ''));
        $website = $this->websiteSummary($brandId, $from, $to);
        $queues = $this->queueCounts($brandId);
        $ai = $this->aiSummary($request);
        $datasets = $this->datasetSyncSummary();

        return [
            'generated_at' => gmdate('c'),
            'range' => [
                'from' => $from,
                'to' => $to,
                'label' => $label,
            ],
            'brand' => [
                'key' => $brand->id(),
                'name' => $brand->name(),
            ],
            'system' => [
                'api_status' => 'ok',
                'api_version' => 'v1',
                'release' => $release !== '' ? $release : null,
                'admin_api_enabled' => (bool) Config::get('admin_api.enabled', false),
                'admin_api_restricted' => (bool) Config::get('admin_api.restricted', true),
            ],
            'website' => $website,
            'queues' => $queues,
            'ai' => $ai,
            'datasets' => $datasets,
            'attention' => $this->attentionItems($queues, $website),
            'warnings' => $this->warnings($website),
            'labels' => [
                'website' => 'Genuine traffic excludes page views classified as bot or unknown device.',
                'searches' => 'Search counts use is_excluded=0 (known abusive/bot traffic filtered).',
                'ai_cost' => 'AI cost is estimated AUD from recorded usage rows when present.',
                'sparse' => 'Zero counts may mean no activity in range or analytics not yet populated.',
            ],
        ];
    }

    /** @return array<string,mixed> */
    public function websiteInsights(Request $request): array
    {
        [$from, $to, $label] = $this->dateRange($request);
        $brandId = AdminApiBrandScope::brandId();
        $filters = $this->insightsFilters($request);
        $labels = [
            'visitors' => 'Distinct sessions excluding bot/unknown page views.',
            'filtered_bot_page_views' => 'Page views classified as bot or unknown — shown separately, not in visitor totals.',
            'figures' => 'Aggregate first-party analytics; incomplete when tracking tables are empty.',
            'daily_searches' => 'Daily filtered searches (is_excluded=0). secondary = no-result count.',
            'daily_contacts' => 'Daily provider contact actions (interest signals, not completed jobs).',
            'filters' => 'Optional location (exact town name or postcode) and device (mobile|tablet|desktop) refine the report. Location applies to search demand; device applies to session-linked traffic and demand.',
        ];

        $empty = [
            'from' => $from,
            'to' => $to,
            'range_label' => $label,
            'brand_key' => AdminApiBrandScope::brand()->id(),
            'available' => true,
            'summary' => [],
            'filtered_bot_page_views' => 0,
            'daily' => [],
            'daily_searches' => [],
            'daily_contacts' => [],
            'daily_profile_views' => [],
            'pages' => [],
            'devices' => [],
            'services' => [],
            'locations' => [],
            'coverage_gaps' => [],
            'actions' => [],
            'providers' => [],
            'filters' => $filters,
            'labels' => $labels,
        ];

        if (!$this->databaseConfigured()) {
            return $empty;
        }

        try {
            $report = WebsiteInsightsService::report($brandId, $from, $to, $filters);
            $botViews = $this->botPageViews($brandId, $from, $to, $filters['device'] ?? null);

            return [
                'from' => $from,
                'to' => $to,
                'range_label' => $label,
                'brand_key' => AdminApiBrandScope::brand()->id(),
                'available' => true,
                'summary' => $report['summary'] ?? [],
                'filtered_bot_page_views' => $botViews,
                'daily' => $report['daily'] ?? [],
                'daily_searches' => $report['daily_searches'] ?? [],
                'daily_contacts' => $report['daily_contacts'] ?? [],
                'daily_profile_views' => $report['daily_profile_views'] ?? [],
                'pages' => $report['pages'] ?? [],
                'devices' => $report['devices'] ?? [],
                'services' => $report['services'] ?? [],
                'locations' => $report['locations'] ?? [],
                'coverage_gaps' => $report['coverage_gaps'] ?? [],
                'actions' => $report['actions'] ?? [],
                'providers' => $report['providers'] ?? [],
                'filters' => $report['filters'] ?? $filters,
                'labels' => $labels,
            ];
        } catch (Throwable) {
            return array_merge($empty, ['available' => false]);
        }
    }

    /**
     * @return array{location:?string,device:?string}
     */
    private function insightsFilters(Request $request): array
    {
        $location = trim((string) $request->query('location', ''));
        $device = strtolower(trim((string) $request->query('device', '')));
        if ($location === '' || mb_strlen($location) > 120) {
            $location = '';
        }
        if (!in_array($device, ['mobile', 'tablet', 'desktop'], true)) {
            $device = '';
        }

        return [
            'location' => $location !== '' ? $location : null,
            'device' => $device !== '' ? $device : null,
        ];
    }

    /**
     * @return array{0:string,1:string,2:string}
     */
    private function dateRange(Request $request): array
    {
        $range = trim((string) $request->query('range', '30d'));
        $from = trim((string) $request->query('from', ''));
        $to = trim((string) $request->query('to', ''));
        if ($from !== '' || $to !== '') {
            $range = 'custom';
        }
        if (!in_array($range, ['7d', '30d', '90d', 'fy', 'pfy', 'custom'], true)) {
            $range = '30d';
        }

        return ReportingService::resolveRange($range, $from, $to);
    }

    /** @return array<string,mixed> */
    private function websiteSummary(int $brandId, string $from, string $to): array
    {
        $unavailable = [
            'available' => false,
            'page_views' => null,
            'visitors' => null,
            'searches' => null,
            'no_result_searches' => null,
            'provider_contacts' => null,
            'profile_views' => null,
            'filtered_bot_page_views' => null,
            'last_page_view_at' => null,
            'sparse' => true,
        ];

        if (!$this->databaseConfigured()) {
            return array_merge($unavailable, [
                'available' => true,
                'page_views' => 0,
                'visitors' => 0,
                'searches' => 0,
                'no_result_searches' => 0,
                'provider_contacts' => 0,
                'profile_views' => 0,
                'filtered_bot_page_views' => 0,
            ]);
        }

        try {
            $report = WebsiteInsightsService::report($brandId, $from, $to);
            $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];

            return [
                'available' => true,
                'page_views' => (int) ($summary['page_views'] ?? 0),
                'visitors' => (int) ($summary['visitors'] ?? 0),
                'searches' => (int) ($summary['searches'] ?? 0),
                'no_result_searches' => (int) ($summary['no_results'] ?? 0),
                'provider_contacts' => (int) ($summary['contact_actions'] ?? 0),
                'profile_views' => (int) ($summary['profile_views'] ?? 0),
                'filtered_bot_page_views' => $this->botPageViews($brandId, $from, $to),
                'last_page_view_at' => $summary['last_page_view_at'] ?? null,
                'sparse' => ((int) ($summary['page_views'] ?? 0)) === 0 && ((int) ($summary['searches'] ?? 0)) === 0,
            ];
        } catch (Throwable) {
            return $unavailable;
        }
    }

    private function botPageViews(int $brandId, string $from, string $to, ?string $device = null): int
    {
        try {
            if (!$this->databaseConfigured() || !Database::tableExists('page_views')) {
                return 0;
            }

            // Device filters are for genuine traffic only; bot/unknown remains
            // an unfiltered companion total unless no device filter is set.
            if ($device !== null && $device !== '') {
                return 0;
            }

            return (int) Database::scalar(
                "SELECT COUNT(*) FROM page_views WHERE brand_id=? AND device_type IN ('bot','unknown') "
                . 'AND created_at BETWEEN ? AND ?',
                [$brandId, $from . ' 00:00:00', $to . ' 23:59:59']
            );
        } catch (Throwable) {
            return 0;
        }
    }

    /** @return array<string,mixed> */
    private function queueCounts(int $brandId): array
    {
        return [
            'claims_pending' => $this->scopedCount('claims:read', fn (): int => $this->countPendingClaims()),
            'corrections_pending' => $this->scopedCount(
                'corrections:read',
                fn (): int => $this->countTableStatus('listing_corrections', 'pending')
            ),
            'duplicates_open' => $this->scopedCount(
                'duplicates:read',
                fn (): int => $this->countOpenDuplicates($brandId)
            ),
            'drafts_pending_review' => $this->scopedCount(
                'drafts:read',
                fn (): int => $this->countDraftsPending($brandId)
            ),
            'facility_candidates_pending' => $this->scopedCount(
                'facilities:read',
                fn (): int => $this->countFacilitiesPending($brandId)
            ),
        ];
    }

    /** @return array{available:bool,count:?int} */
    private function scopedCount(string $scope, callable $counter): array
    {
        if (!AdminApiContext::hasScope($scope)) {
            return ['available' => false, 'count' => null];
        }
        try {
            return ['available' => true, 'count' => (int) $counter()];
        } catch (Throwable) {
            return ['available' => true, 'count' => null];
        }
    }

    private function databaseConfigured(): bool
    {
        $cfg = Config::get('database');

        return is_array($cfg) && trim((string) ($cfg['host'] ?? '')) !== '';
    }

    private function countPendingClaims(): int
    {
        if (!$this->databaseConfigured()) {
            return 0;
        }
        $total = 0;
        if (Database::tableExists('caravan_park_claims')) {
            $total += (int) Database::scalar(
                "SELECT COUNT(*) FROM caravan_park_claims WHERE status = 'pending'"
            );
        }
        if (Database::tableExists('provider_claim_tokens')) {
            $total += (int) Database::scalar(
                'SELECT COUNT(*) FROM provider_claim_tokens WHERE used_at IS NULL AND expires_at > NOW()'
            );
        }

        return $total;
    }

    private function countTableStatus(string $table, string $status): int
    {
        if (!$this->databaseConfigured() || !Database::tableExists($table)) {
            return 0;
        }

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM {$table} WHERE status = ?",
            [$status]
        );
    }

    private function countOpenDuplicates(int $brandId): int
    {
        if (!$this->databaseConfigured() || !Database::tableExists('api_duplicate_decisions')) {
            return 0;
        }

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM api_duplicate_decisions WHERE brand_id = ? AND status = 'open'",
            [$brandId]
        );
    }

    private function countDraftsPending(int $brandId): int
    {
        if (!$this->databaseConfigured() || !Database::tableExists('api_drafts')) {
            return 0;
        }

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM api_drafts WHERE brand_id = ? AND status = 'pending_review'",
            [$brandId]
        );
    }

    private function countFacilitiesPending(int $brandId): int
    {
        if (!$this->databaseConfigured() || !Database::tableExists('traveller_facilities')) {
            return 0;
        }

        return (int) Database::scalar(
            "SELECT COUNT(*) FROM traveller_facilities WHERE deleted_at IS NULL "
            . "AND (brand_id = ? OR brand_id IS NULL) AND status IN ('pending','pending_review','draft')",
            [$brandId]
        );
    }

    /** @return array<string,mixed> */
    private function aiSummary(Request $request): array
    {
        if (!AdminApiContext::hasScope('ai:read')) {
            return ['available' => false];
        }
        if (!$this->databaseConfigured()) {
            return [
                'available' => true,
                'requests' => 0,
                'estimated_cost_aud' => 0.0,
                'cache_hits' => 0,
                'ai_enabled' => false,
                'openai_enabled' => false,
                'success_rate' => null,
                'sparse' => true,
                'from' => null,
                'to' => null,
            ];
        }
        try {
            $summary = (new AdminApiAiUsageService())->summary($request);
            $requests = (int) ($summary['requests'] ?? 0);

            return [
                'available' => true,
                'requests' => $requests,
                'estimated_cost_aud' => (float) ($summary['estimated_cost_aud'] ?? 0),
                'cache_hits' => (int) ($summary['cache_hits'] ?? 0),
                'ai_enabled' => (bool) ($summary['ai_enabled'] ?? false),
                'openai_enabled' => (bool) ($summary['openai_enabled'] ?? false),
                'success_rate' => null,
                'sparse' => (bool) ($summary['sparse'] ?? true),
                'from' => $summary['from'] ?? null,
                'to' => $summary['to'] ?? null,
            ];
        } catch (Throwable) {
            // Scoped but tables/DB unavailable — still expose the card with empty figures.
            return [
                'available' => true,
                'requests' => 0,
                'estimated_cost_aud' => 0.0,
                'cache_hits' => 0,
                'ai_enabled' => false,
                'openai_enabled' => false,
                'success_rate' => null,
                'sparse' => true,
                'from' => null,
                'to' => null,
            ];
        }
    }

    /** @return array<string,mixed> */
    private function datasetSyncSummary(): array
    {
        if (!AdminApiContext::hasScope('datasets:read')) {
            return ['available' => false];
        }
        try {
            if (!$this->databaseConfigured() || !Database::tableExists('government_datasets')) {
                return [
                    'available' => true,
                    'dataset_count' => 0,
                    'last_imported_at' => null,
                    'last_checked_at' => null,
                ];
            }
            $row = Database::selectOne(
                'SELECT COUNT(*) AS dataset_count, MAX(last_imported_at) AS last_imported_at, '
                . 'MAX(last_checked_at) AS last_checked_at FROM government_datasets'
            ) ?? [];

            return [
                'available' => true,
                'dataset_count' => (int) ($row['dataset_count'] ?? 0),
                'last_imported_at' => $row['last_imported_at'] ?? null,
                'last_checked_at' => $row['last_checked_at'] ?? null,
            ];
        } catch (Throwable) {
            return [
                'available' => true,
                'dataset_count' => 0,
                'last_imported_at' => null,
                'last_checked_at' => null,
            ];
        }
    }

    /**
     * @param array<string,mixed> $queues
     * @param array<string,mixed> $website
     * @return list<array{code:string,count:?int,message:string}>
     */
    private function attentionItems(array $queues, array $website): array
    {
        $items = [];
        foreach (
            [
                'claims_pending' => 'Claims awaiting review',
                'corrections_pending' => 'Listing corrections awaiting review',
                'duplicates_open' => 'Open duplicate candidates',
                'drafts_pending_review' => 'Import drafts awaiting review',
                'facility_candidates_pending' => 'Facility candidates awaiting review',
            ] as $key => $message
        ) {
            $block = $queues[$key] ?? null;
            if (!is_array($block) || empty($block['available'])) {
                continue;
            }
            $count = $block['count'];
            if (is_int($count) && $count > 0) {
                $items[] = ['code' => $key, 'count' => $count, 'message' => $message];
            }
        }
        if (!empty($website['available']) && (int) ($website['no_result_searches'] ?? 0) > 0) {
            $items[] = [
                'code' => 'no_result_searches',
                'count' => (int) $website['no_result_searches'],
                'message' => 'Searches with no results in selected period',
            ];
        }

        return $items;
    }

    /**
     * @param array<string,mixed> $website
     * @return list<array{code:string,message:string}>
     */
    private function warnings(array $website): array
    {
        if (isset($website['available']) && $website['available'] === false) {
            return [[
                'code' => 'website_insights_unavailable',
                'message' => 'Website traffic figures could not be loaded for this brand.',
            ]];
        }

        return [];
    }
}
