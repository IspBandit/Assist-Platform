<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\CsvExport;
use App\Services\FeatureFlag;
use App\Services\Settings;
use App\Services\Demand\ReportingService;
use App\Services\Demand\WebsiteInsightsService;

/**
 * Platform-wide demand, provider-usage, funnel, coverage-gap and demand-map
 * reporting for administrators, plus permission-controlled CSV exports. All
 * reads are date-bounded and use the aggregate/indexed analytics tables so the
 * dashboards stay fast on shared hosting.
 */
final class DemandController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);
        $brand = current_brand();

        return $this->view('admin.demand.index', [
            'title'    => 'Website insights',
            'range'    => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'brand'    => $brand,
            'insights' => WebsiteInsightsService::report($brand->databaseId(), $from, $to),
            'pageTrackingOn' => (string) Settings::get('analytics_enabled', '0') === '1',
            'demandTrackingOn' => FeatureFlag::enabled('demand_analytics', false),
        ]);
    }

    public function providers(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);
        $report = WebsiteInsightsService::report(current_brand()->databaseId(), $from, $to);

        return $this->view('admin.demand.providers', [
            'title'   => 'Provider interest',
            'range'   => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'rows'    => $report['providers'],
        ]);
    }

    public function funnel(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);
        $report = WebsiteInsightsService::report(current_brand()->databaseId(), $from, $to);
        $summary = $report['summary'];
        $rawStages = [
            ['Searches', (int) $summary['searches']],
            ['Provider profiles opened', (int) $summary['profile_views']],
            ['Contact actions', (int) $summary['contact_actions']],
            ['Confirmed provider use', (int) $summary['confirmed_uses']],
        ];
        $funnel = [];
        $previous = null;
        foreach ($rawStages as [$stageLabel, $count]) {
            $funnel[] = ['label' => $stageLabel, 'count' => $count, 'rate' => $previous === null ? null : ReportingService::rate($count, $previous)];
            $previous = $count;
        }

        return $this->view('admin.demand.funnel', [
            'title'  => 'Conversion funnel',
            'range'  => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'funnel' => $funnel,
        ]);
    }

    public function coverage(Request $request): Response
    {
        $this->requirePermission('demand.view');
        return $this->redirectWith('/admin/demand', 'info', 'Coverage and zero-result searches are now included in Website insights.');
    }

    public function map(Request $request): Response
    {
        $this->requirePermission('demand.view');
        return $this->redirectWith('/admin/demand', 'info', 'Location demand is now included in Website insights.');
    }

    /** Permission-controlled CSV exports honouring the active filters. */
    public function export(Request $request): Response
    {
        $this->requirePermission('demand.export');
        [$from, $to,, $range] = $this->range($request);
        $type = (string) $request->input('type', 'overview');

        AuditLog::record('demand.export', 'report', $type, null, $from . '..' . $to);

        switch ($type) {
            case 'providers':
                $rows = array_map(static fn ($r) => [
                    $r['provider_id'], $r['label'], $r['impressions'], $r['profile_views'], $r['contacts'],
                    $r['confirmed_uses'] ?? 0, $r['impression_to_profile_rate'], $r['profile_to_contact_rate'],
                ], WebsiteInsightsService::report(current_brand()->databaseId(), $from, $to)['providers']);
                return CsvExport::download(
                    "provider-interest_{$from}_{$to}.csv",
                    ['Provider ID', 'Business', 'Result appearances', 'Profile views', 'Contact actions', 'Confirmed uses', 'Appearance to profile %', 'Profile to contact %'],
                    $rows
                );

            case 'monetisation':
                $report = WebsiteInsightsService::report(current_brand()->databaseId(), $from, $to);
                $rows = [];
                foreach ($report['providers_used'] as $r) {
                    $rows[] = ['provider_used', $r['provider_id'], $r['label'], $r['confirmed_uses'], $r['distinct_customers'], ''];
                }
                foreach ($report['providers_contacted'] as $r) {
                    $rows[] = ['provider_contacted', $r['provider_id'], $r['label'], $r['contacts'], $r['phone'], $r['requests'] ?? 0];
                }
                foreach ($report['stays_engaged'] as $r) {
                    $rows[] = ['stay_engaged', $r['park_id'], $r['label'], $r['contacts'] ?? 0, $r['assistance_requests'], $r['page_views']];
                }
                return CsvExport::download(
                    "monetisation-shortlist_{$from}_{$to}.csv",
                    ['Signal', 'Entity ID', 'Name', 'Primary count', 'Secondary count', 'Tertiary count'],
                    $rows
                );

            case 'funnel':
                $summary = WebsiteInsightsService::report(current_brand()->databaseId(), $from, $to)['summary'];
                $raw = [['Searches', (int) $summary['searches']], ['Provider profiles opened', (int) $summary['profile_views']],
                    ['Contact actions', (int) $summary['contact_actions']], ['Confirmed provider use', (int) $summary['confirmed_uses']]];
                $rows = [];
                $previous = null;
                foreach ($raw as [$stage, $count]) {
                    $rate = $previous === null ? null : ReportingService::rate($count, $previous);
                    $rows[] = [$stage, $count, $rate === null ? '' : $rate . '%'];
                    $previous = $count;
                }
                return CsvExport::download("funnel_{$from}_{$to}.csv", ['Stage', 'Count', 'Conversion from previous'], $rows);

            case 'overview':
            default:
                $o = WebsiteInsightsService::report(current_brand()->databaseId(), $from, $to)['summary'];
                $rows = [];
                foreach ($o as $k => $v) {
                    $rows[] = [$k, $v];
                }
                return CsvExport::download("demand-overview_{$from}_{$to}.csv", ['Metric', 'Value'], $rows);
        }
    }

    /**
     * @return array{0:string,1:string,2:string,3:string} [from, to, label, range]
     */
    private function range(Request $request): array
    {
        $range = (string) $request->input('range', '30d');
        [$from, $to, $label] = ReportingService::resolveRange(
            $range,
            (string) $request->input('from', ''),
            (string) $request->input('to', '')
        );
        return [$from, $to, $label, $range];
    }
}
