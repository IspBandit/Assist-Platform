<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\CsvExport;
use App\Services\Demand\ReportingService;

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

        return $this->view('admin.demand.index', [
            'title'    => 'Demand analytics',
            'range'    => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'overview' => ReportingService::platformOverview($from, $to),
            'byCategory' => ReportingService::needsBy('category', $from, $to, 10),
            'byUrgency'  => ReportingService::needsBy('urgency', $from, $to),
            'byState'    => ReportingService::needsBy('state', $from, $to),
            'byTown'     => ReportingService::needsBy('town', $from, $to, 10),
            'byVehicle'  => ReportingService::needsBy('vehicle_type', $from, $to),
        ]);
    }

    public function providers(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);

        return $this->view('admin.demand.providers', [
            'title'   => 'Provider usage',
            'range'   => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'rows'    => ReportingService::providerPerformance($from, $to, 200),
        ]);
    }

    public function funnel(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);

        return $this->view('admin.demand.funnel', [
            'title'  => 'Conversion funnel',
            'range'  => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'funnel' => ReportingService::funnel($from, $to),
        ]);
    }

    public function coverage(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);

        return $this->view('admin.demand.coverage', [
            'title'    => 'Coverage gaps',
            'range'    => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'coverage' => ReportingService::coverageGaps($from, $to),
        ]);
    }

    public function map(Request $request): Response
    {
        $this->requirePermission('demand.view');
        [$from, $to, $label, $range] = $this->range($request);

        return $this->view('admin.demand.map', [
            'title' => 'Demand map',
            'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $label,
            'mapData' => ReportingService::demandMap($from, $to),
        ]);
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
                    $r['provider_id'], $r['business_name'], $r['is_verified'] ? 'yes' : 'no',
                    $r['impressions'], $r['profile_views'], $r['contacts'], $r['engagements'],
                    $r['customer_confirmed'], $r['provider_confirmed'], $r['mutually_confirmed'], $r['cancellations'],
                ], ReportingService::providerPerformance($from, $to, 5000));
                return CsvExport::download(
                    "provider-usage_{$from}_{$to}.csv",
                    ['Provider ID', 'Business', 'Verified', 'Impressions', 'Profile views', 'Contacts', 'Engagements',
                        'Customer-confirmed', 'Provider-reported', 'Mutually confirmed', 'Cancellations'],
                    $rows
                );

            case 'funnel':
                $rows = array_map(static fn ($s) => [$s['label'], $s['count'], $s['rate'] === null ? '' : $s['rate'] . '%'], ReportingService::funnel($from, $to));
                return CsvExport::download("funnel_{$from}_{$to}.csv", ['Stage', 'Count', 'Conversion from previous'], $rows);

            case 'demand_category':
                $rows = array_map(static fn ($r) => [$r['name'], $r['c']], ReportingService::needsBy('category', $from, $to, 1000));
                return CsvExport::download("demand-by-category_{$from}_{$to}.csv", ['Category', 'Needs'], $rows);

            case 'demand_town':
                $rows = array_map(static fn ($r) => [$r['name'], $r['c']], ReportingService::needsBy('town', $from, $to, 5000));
                return CsvExport::download("demand-by-town_{$from}_{$to}.csv", ['Town', 'Needs'], $rows);

            case 'overview':
            default:
                $o = ReportingService::platformOverview($from, $to);
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
