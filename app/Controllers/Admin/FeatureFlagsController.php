<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\FeatureFlag;

/**
 * Toggles database-backed feature flags. The master billing switch stays in
 * .env (ENABLE_BILLING); these govern in-app future features as they are
 * wired up, and default off for launch.
 */
final class FeatureFlagsController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('feature_flags.manage');
        return $this->view('admin.feature-flags.index', [
            'title' => 'Feature flags',
            'flags' => FeatureFlag::all(),
            'billingEnv' => (bool) config('billing.enabled', false),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('feature_flags.manage');

        $submitted = (array) $request->input('flags', []);
        foreach (FeatureFlag::all() as $flag) {
            $key = (string) $flag['flag_key'];
            FeatureFlag::set($key, isset($submitted[$key]));
        }
        AuditLog::record('feature_flags.update', 'feature_flags', null);
        return $this->redirectWith('/admin/feature-flags', 'success', 'Feature flags saved.');
    }
}
