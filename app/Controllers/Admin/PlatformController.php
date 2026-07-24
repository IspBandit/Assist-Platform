<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Platform\Brand\BrandRegistry;
use App\Services\AdminBrandAccess;
use App\Services\AuditLog;
use App\Services\BrandBlueprintService;
use App\Services\GraphMailHealth;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PlatformController extends Controller
{
    public function controlCentre(Request $request): Response
    {
        if (!auth()->isSuperAdmin() && !auth()->hasAnyRole('administrator', 'platform-administrator')) {
            $this->abort(403);
        }
        $brands = AdminBrandAccess::availableBrands((int) auth()->id());
        $brandStats = [];
        foreach ($brands as $key => $brand) {
            $brandStats[$key] = [
                'providers' => $this->count('SELECT COUNT(*) FROM provider_brand_listings WHERE brand_id = ? AND status = ?', [$brand->databaseId(), 'active']),
                'categories' => $this->count('SELECT COUNT(*) FROM brand_service_categories WHERE brand_id = ? AND is_active = 1', [$brand->databaseId()]),
                'assets' => $this->count('SELECT COUNT(*) FROM social_media_assets WHERE brand_id = ? AND status <> ?', [$brand->databaseId(), 'archived']),
            ];
        }
        return $this->view('admin.platform.control-centre', [
            'title' => 'Platform control centre',
            'brands' => $brands,
            'brandStats' => $brandStats,
            'totals' => [
                'users' => $this->count('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'),
                'providers' => $this->count('SELECT COUNT(*) FROM providers WHERE deleted_at IS NULL'),
                'memberships' => $this->count("SELECT COUNT(*) FROM subscriptions WHERE status IN ('trialing','active','past_due')"),
                'queued_email' => $this->count("SELECT COUNT(*) FROM email_queue WHERE status IN ('queued','processing')"),
                'failed_email' => $this->count("SELECT COUNT(*) FROM email_queue WHERE status = 'failed'"),
            ],
            'tasks' => $this->safe(fn () => Database::select('SELECT task_key, last_status, last_run_at FROM scheduled_tasks ORDER BY task_key')),
            'migrations' => $this->safe(fn () => Database::select('SELECT migration, batch, status, completed_at FROM migrations ORDER BY id DESC LIMIT 8')),
            'graphMail' => GraphMailHealth::inspect((array) config('mail')),
        ]);
    }

    public function brandBuilder(Request $request): Response
    {
        $this->requirePlatformAdministrator();

        return $this->view('admin.platform.brand-builder', [
            'title' => 'Brand Builder',
            'moduleOptions' => BrandBlueprintService::MODULES,
            'blueprint' => null,
            'values' => [],
            'error' => null,
        ]);
    }

    public function previewBrand(Request $request): Response
    {
        $this->requirePlatformAdministrator();
        $values = [
            'brand_key' => $request->input('brand_key'),
            'name' => $request->input('name'),
            'domain' => $request->input('domain'),
            'primary_colour' => $request->input('primary_colour'),
            'accent_colour' => $request->input('accent_colour'),
            'modules' => $request->input('modules', []),
        ];
        $blueprint = null;
        $error = null;
        try {
            $registry = BrandRegistry::fromArray((array) config('brands.registry', []));
            $blueprint = (new BrandBlueprintService())->build($values, $registry);
            AuditLog::record('admin.brand_blueprint_previewed', 'brand_blueprint', (string) $blueprint['brand_key']);
        } catch (InvalidArgumentException $exception) {
            $error = $exception->getMessage();
        }

        return $this->view('admin.platform.brand-builder', [
            'title' => 'Brand Builder',
            'moduleOptions' => BrandBlueprintService::MODULES,
            'blueprint' => $blueprint,
            'values' => $values,
            'error' => $error,
        ]);
    }

    public function switchBrand(Request $request): Response
    {
        if (!auth()->hasAnyRole('super-administrator', 'administrator', 'platform-administrator', 'brand-administrator', 'moderator', 'editor', 'support', 'finance', 'marketing')) {
            $this->abort(403);
        }
        $targetKey = trim((string) $request->input('brand'));
        $registry = BrandRegistry::fromArray((array) config('brands.registry', []));
        $target = $registry->find($targetKey);
        if ($target === null || $target->status() === 'disabled') { $this->abort(404); }
        try {
            $token = AdminBrandAccess::issue((int) auth()->id(), current_brand(), $target, (string) $request->input('return_path', '/admin'));
            AuditLog::record('admin.brand_handoff_issued', 'brand', (string) $target->databaseId());
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin', 'error', $e->getMessage());
        }
        return $this->redirect($target->url() . '/admin/brand-handoff?token=' . rawurlencode($token));
    }

    public function consumeHandoff(Request $request): Response
    {
        $result = AdminBrandAccess::consume(trim((string) $request->query('token')), current_brand());
        if ($result === null) {
            return $this->redirectWith('/login', 'error', 'That secure admin switch link is invalid or has expired.');
        }
        Auth::instance()->login($result['user_id']);
        AuditLog::record('admin.brand_handoff_consumed', 'brand', (string) current_brand()->databaseId());
        return $this->redirect($result['return_path']);
    }

    /** @param array<int,mixed> $params */
    private function count(string $sql, array $params = []): int
    {
        try { return (int) Database::scalar($sql, $params); } catch (Throwable) { return 0; }
    }

    /** @return array<int,array<string,mixed>> */
    private function safe(callable $callback): array
    {
        try { return $callback(); } catch (Throwable) { return []; }
    }

    private function requirePlatformAdministrator(): void
    {
        if (!auth()->isSuperAdmin() && !auth()->hasAnyRole('administrator', 'platform-administrator')) {
            $this->abort(403);
        }
    }
}
