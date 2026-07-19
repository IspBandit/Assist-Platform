<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\CsvExport;
use Throwable;

/**
 * Operational reporting: demand by location/category, the request conversion
 * funnel, provider/run/park summaries, traffic and email health. Every table
 * is also downloadable as CSV.
 */
final class ReportsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('reports.view');

        return $this->view('admin.reports.index', [
            'title'        => 'Reports',
            'funnel'       => $this->funnel(),
            'demandTowns'  => $this->demandByTown(),
            'demandCats'   => $this->demandByCategory(),
            'providers'    => $this->providerSummary(),
            'runs'         => $this->runSummary(),
            'parks'        => $this->parkSummary(),
            'email'        => $this->emailSummary(),
            'traffic'      => $this->trafficSummary(),
            'analyticsOn'  => (string) \App\Services\Settings::get('analytics_enabled', '0') === '1',
        ]);
    }

    public function export(Request $request): Response
    {
        $this->requirePermission('reports.view');
        $report = (string) $request->input('report', '');
        $stamp = date('Ymd');

        return match ($report) {
            'demand_towns' => CsvExport::download("demand-by-town-{$stamp}.csv", ['Town', 'Region', 'Total requests', 'Open', 'Completed'], $this->demandByTown()),
            'demand_categories' => CsvExport::download("demand-by-category-{$stamp}.csv", ['Category', 'Total requests', 'Open', 'Completed'], $this->demandByCategory()),
            'funnel' => CsvExport::download("request-funnel-{$stamp}.csv", ['Stage', 'Requests'], $this->funnel()),
            'providers' => CsvExport::download("providers-{$stamp}.csv", ['Business name', 'Status', 'Verified', 'Town', 'Region', 'Created'], $this->providersExport()),
            'requests' => CsvExport::download("requests-{$stamp}.csv", ['Reference', 'Status', 'Town', 'Category', 'Urgency', 'Source', 'Created'], $this->requestsExport()),
            'runs' => CsvExport::download("service-runs-{$stamp}.csv", ['Title', 'Provider', 'Status', 'Start date', 'Booked', 'Capacity'], $this->runsExport()),
            default => $this->redirectWith('/admin/reports', 'error', 'Unknown report.'),
        };
    }

    // ----- Report queries (return plain arrays, safe on partial schema) -----

    /** @return array<int,array<string,mixed>> */
    private function funnel(): array
    {
        $stages = [
            'Submitted'        => "status NOT IN ('draft')",
            'Verified / open'  => "status IN ('open','matching','provider_interested','information_requested','offered_appointment','added_to_run','accepted','in_progress','completed')",
            'Matched'          => "status IN ('provider_interested','offered_appointment','added_to_run','accepted','in_progress','completed')",
            'Added to a run'   => "status IN ('added_to_run','accepted','in_progress','completed')",
            'Completed'        => "status = 'completed'",
        ];
        $out = [];
        foreach ($stages as $label => $where) {
            $out[] = ['stage' => $label, 'count' => $this->scalar("SELECT COUNT(*) FROM service_requests WHERE deleted_at IS NULL AND is_demo = 0 AND {$where}")];
        }
        return $out;
    }

    /** @return array<int,array<string,mixed>> */
    private function demandByTown(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT t.name AS town, r.name AS region, "
            . "COUNT(sr.id) AS total, "
            . "SUM(sr.status IN ('open','matching')) AS open_count, "
            . "SUM(sr.status = 'completed') AS completed "
            . "FROM service_requests sr "
            . "INNER JOIN towns t ON t.id = sr.town_id "
            . "LEFT JOIN regions r ON r.id = sr.region_id "
            . "WHERE sr.deleted_at IS NULL AND sr.is_demo = 0 "
            . "GROUP BY t.id, t.name, r.name ORDER BY total DESC LIMIT 100"
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function demandByCategory(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT c.name AS category, COUNT(sr.id) AS total, "
            . "SUM(sr.status IN ('open','matching')) AS open_count, "
            . "SUM(sr.status = 'completed') AS completed "
            . "FROM service_requests sr "
            . "INNER JOIN service_categories c ON c.id = sr.primary_category_id "
            . "WHERE sr.deleted_at IS NULL AND sr.is_demo = 0 "
            . "GROUP BY c.id, c.name ORDER BY total DESC LIMIT 100"
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function providerSummary(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT status, COUNT(*) AS total, SUM(is_verified = 1) AS verified "
            . "FROM providers WHERE deleted_at IS NULL GROUP BY status ORDER BY status"
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function runSummary(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT status, COUNT(*) AS total, COALESCE(SUM(bookings_count),0) AS booked "
            . "FROM service_runs WHERE deleted_at IS NULL GROUP BY status ORDER BY status"
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function parkSummary(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT status, COUNT(*) AS total FROM caravan_parks WHERE deleted_at IS NULL GROUP BY status ORDER BY status"
        ));
    }

    /** @return array<string,int> */
    private function emailSummary(): array
    {
        $stats = ['pending' => 0, 'sent' => 0, 'failed' => 0];
        foreach ($this->safe(fn () => Database::select('SELECT status, COUNT(*) AS c FROM email_queue GROUP BY status')) as $row) {
            $stats[(string) $row['status']] = (int) $row['c'];
        }
        return $stats;
    }

    /** @return array<int,array<string,mixed>> */
    private function trafficSummary(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT route, COUNT(*) AS views FROM page_views "
            . "WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) "
            . "GROUP BY route ORDER BY views DESC LIMIT 20"
        ));
    }

    // ----- Raw exports ------------------------------------------------------

    /** @return array<int,array<string,mixed>> */
    private function providersExport(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT p.business_name, p.status, IF(p.is_verified, 'yes', 'no') AS verified, "
            . "t.name AS town, r.name AS region, p.created_at "
            . "FROM providers p LEFT JOIN towns t ON t.id = p.base_town_id LEFT JOIN regions r ON r.id = p.region_id "
            . "WHERE p.deleted_at IS NULL ORDER BY p.created_at DESC"
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function requestsExport(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT sr.reference, sr.status, t.name AS town, c.name AS category, sr.urgency, sr.source, sr.created_at "
            . "FROM service_requests sr LEFT JOIN towns t ON t.id = sr.town_id "
            . "LEFT JOIN service_categories c ON c.id = sr.primary_category_id "
            . "WHERE sr.deleted_at IS NULL AND sr.is_demo = 0 ORDER BY sr.created_at DESC LIMIT 5000"
        ));
    }

    /** @return array<int,array<string,mixed>> */
    private function runsExport(): array
    {
        return $this->safe(fn () => Database::select(
            "SELECT r.title, p.business_name, r.status, r.start_date, r.bookings_count, r.appointments_total "
            . "FROM service_runs r INNER JOIN providers p ON p.id = r.provider_id "
            . "WHERE r.deleted_at IS NULL ORDER BY r.start_date DESC LIMIT 5000"
        ));
    }

    private function scalar(string $sql): int
    {
        try {
            return (int) Database::scalar($sql);
        } catch (Throwable) {
            return 0;
        }
    }

    /** @param callable():array<int,array<string,mixed>> $fn @return array<int,array<string,mixed>> */
    private function safe(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }
}
