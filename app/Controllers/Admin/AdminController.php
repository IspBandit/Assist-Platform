<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\FoundingGraphicService;
use App\Services\Settings;
use Throwable;

final class AdminController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $stats = [
            'new_requests'        => $this->count("SELECT COUNT(*) FROM service_requests WHERE status IN ('pending_moderation','awaiting_verification')"),
            'open_requests'       => $this->count("SELECT COUNT(*) FROM service_requests WHERE status IN ('open','matching')"),
            'pending_providers'   => $this->count("SELECT COUNT(*) FROM providers WHERE status = 'pending'"),
            'pending_documents'   => $this->count("SELECT COUNT(*) FROM provider_documents WHERE verification_status = 'pending'"),
            'active_providers'    => $this->count("SELECT COUNT(*) FROM providers WHERE status = 'active'"),
            'active_runs'         => $this->count("SELECT COUNT(*) FROM service_runs WHERE status IN ('forming','confirmed','limited')"),
            'forming_runs'        => $this->count("SELECT COUNT(*) FROM service_runs WHERE status = 'forming'"),
            'confirmed_runs'      => $this->count("SELECT COUNT(*) FROM service_runs WHERE status = 'confirmed'"),
            'customers'           => $this->count("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL"),
            'parks'               => $this->count("SELECT COUNT(*) FROM caravan_parks WHERE status = 'active'"),
            'prospects'           => $this->count("SELECT COUNT(*) FROM provider_prospects WHERE deleted_at IS NULL"),
            'failed_emails'       => $this->count("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'"),
            'ad_graphics_queue'   => FoundingGraphicService::queueCount(),
        ];

        $recentActivity = $this->safe(fn () => Database::select(
            'SELECT a.action, a.object_type, a.object_id, a.created_at, u.name AS user_name '
            . 'FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id '
            . 'ORDER BY a.id DESC LIMIT 12'
        ));

        $tasks = $this->safe(fn () => Database::select(
            'SELECT task_key, last_status, last_run_at FROM scheduled_tasks ORDER BY task_key'
        ));

        return $this->view('admin.dashboard', [
            'title'          => 'Dashboard',
            'stats'          => $stats,
            'recentActivity' => $recentActivity,
            'tasks'          => $tasks,
            'launchMode'     => Settings::launchMode(),
            'maintenance'    => Settings::isMaintenanceMode(),
            'adGraphicsQueue' => FoundingGraphicService::queueCount(),
        ]);
    }

    private function count(string $sql): int
    {
        try {
            return (int) Database::scalar($sql);
        } catch (Throwable) {
            return 0;
        }
    }

    private function safe(callable $fn): array
    {
        try {
            return $fn();
        } catch (Throwable) {
            return [];
        }
    }
}
