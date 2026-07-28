<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Settings;
use Throwable;

final class AdminController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $brand = current_brand();
        $brandId = $brand->databaseId();
        $stats = [
            'pending_providers' => $this->count(
                "SELECT COUNT(DISTINCT p.id) FROM providers p JOIN provider_brand_listings l ON l.provider_id=p.id WHERE l.brand_id=? AND p.status='pending' AND p.deleted_at IS NULL",
                [$brandId]
            ),
            'pending_documents' => $this->count(
                "SELECT COUNT(DISTINCT d.id) FROM provider_documents d JOIN provider_brand_listings l ON l.provider_id=d.provider_id WHERE l.brand_id=? AND d.verification_status='pending'",
                [$brandId]
            ),
            'active_providers' => $this->count(
                "SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id=? AND status='active' AND search_visible=1 AND deleted_at IS NULL",
                [$brandId]
            ),
            'brand_accounts' => $this->count(
                "SELECT COUNT(*) FROM user_brand_profiles WHERE brand_id=? AND status='active' AND deleted_at IS NULL",
                [$brandId]
            ),
            'failed_emails' => $this->count("SELECT COUNT(*) FROM email_queue WHERE brand_id=? AND status='failed'", [$brandId]),
            'social_assets' => $this->count("SELECT COUNT(*) FROM social_media_assets WHERE brand_id=? AND status<>'archived'", [$brandId]),
        ];

        if ($brand->id() === 'vanassist') {
            $stats += [
                'new_requests' => $this->count("SELECT COUNT(*) FROM service_requests WHERE status IN ('pending_moderation','awaiting_verification')"),
                'open_requests' => $this->count("SELECT COUNT(*) FROM service_requests WHERE status IN ('open','matching')"),
                'active_runs' => $this->count("SELECT COUNT(*) FROM service_runs WHERE status IN ('forming','confirmed','limited')"),
                'customers' => $this->count("SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL"),
                'parks' => $this->count("SELECT COUNT(*) FROM caravan_parks WHERE status = 'active'"),
                'prospects' => $this->count("SELECT COUNT(*) FROM provider_prospects WHERE deleted_at IS NULL"),
            ];
        } elseif ($brand->id() === 'towsmart') {
            $stats['saved_combinations'] = $this->count('SELECT COUNT(*) FROM towing_combinations WHERE brand_id=?', [$brandId]);
        } elseif ($brand->id() === 'trailerwise') {
            $stats['trailer_listings'] = $this->count("SELECT COUNT(*) FROM trailer_listings WHERE brand_id=? AND status<>'archived' AND deleted_at IS NULL", [$brandId]);
        } elseif ($brand->id() === 'localtorque') {
            $stats['regulatory_documents'] = $this->count('SELECT COUNT(*) FROM regulatory_document_brands WHERE brand_id=?', [$brandId]);
            $stats['motorsport_venues'] = $this->count('SELECT COUNT(*) FROM motorsport_venues WHERE is_public=1');
        }

        $canViewAudit = can('audit.view');
        $canViewHealth = can('platform.health');
        $recentActivity = $canViewAudit ? $this->safe(fn () => Database::select(
            'SELECT a.action, a.object_type, a.object_id, a.created_at, u.name AS user_name '
            . 'FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id '
            . 'ORDER BY a.id DESC LIMIT 12'
        )) : [];

        $tasks = $canViewHealth ? $this->safe(fn () => Database::select(
            'SELECT task_key, last_status, last_run_at FROM scheduled_tasks ORDER BY task_key'
        )) : [];

        return $this->view('admin.dashboard', [
            'title'          => 'Dashboard',
            'dashboardBrand' => $brand,
            'stats'          => $stats,
            'recentActivity' => $recentActivity,
            'tasks'          => $tasks,
            'canViewAudit'   => $canViewAudit,
            'canViewHealth'  => $canViewHealth,
            'launchMode'     => Settings::launchMode(),
            'maintenance'    => Settings::isMaintenanceMode(),
        ]);
    }

    /** @param array<int,mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        try {
            return (int) Database::scalar($sql, $params);
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
