<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Billing\BillingManager;
use App\Billing\Entitlements;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;

/**
 * Administrator billing management. Plans, limits and entitlements are editable
 * online here even while ENABLE_BILLING=false, so administrators can configure
 * future pricing privately. All changes are audit logged.
 */
final class BillingController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('billing.manage');

        $plans = Database::select(
            'SELECT id, internal_name, public_name, slug, monthly_price_cents, annual_price_cents, '
            . 'is_active, is_public, is_recommended, is_legacy, display_order '
            . 'FROM billing_plans ORDER BY display_order, id'
        );

        $flags = Database::select('SELECT flag_key, is_enabled, description FROM feature_flags ORDER BY flag_key');
        $taxReview = (string) (Database::scalar("SELECT setting_value FROM tax_settings WHERE setting_key = 'review_status'") ?: 'unknown');

        return $this->view('admin.billing.index', [
            'title'          => 'Billing & plans',
            'plans'          => $plans,
            'flags'          => $flags,
            'billingEnabled' => BillingManager::enabled(),
            'gateway'        => config('billing.gateway', 'none'),
            'taxReview'      => $taxReview,
        ]);
    }

    public function editPlan(Request $request): Response
    {
        $this->requirePermission('billing.manage');
        $planId = (int) $request->input('id');

        $plan = Database::selectOne('SELECT * FROM billing_plans WHERE id = ?', [$planId]);
        if ($plan === null) {
            $this->abort(404, 'Plan not found.');
        }

        $limits = $this->mapRows(
            Database::select('SELECT limit_key, limit_value FROM billing_plan_limits WHERE plan_id = ?', [$planId]),
            'limit_key',
            'limit_value'
        );
        $features = $this->mapRows(
            Database::select('SELECT feature_key, is_enabled FROM billing_plan_features WHERE plan_id = ?', [$planId]),
            'feature_key',
            'is_enabled'
        );

        return $this->view('admin.billing.edit-plan', [
            'title'         => 'Edit plan: ' . $plan['public_name'],
            'plan'          => $plan,
            'limits'        => $limits,
            'features'      => $features,
            'limitKeys'     => Entitlements::limits(),
            'featureKeys'   => Entitlements::features(),
        ]);
    }

    public function updatePlan(Request $request): Response
    {
        $this->requirePermission('billing.manage');
        $planId = (int) $request->input('id');

        $plan = Database::selectOne('SELECT public_name FROM billing_plans WHERE id = ?', [$planId]);
        if ($plan === null) {
            $this->abort(404, 'Plan not found.');
        }

        Database::query(
            'UPDATE billing_plans SET internal_name = ?, public_name = ?, description = ?, '
            . 'monthly_price_cents = ?, annual_price_cents = ?, trial_days = ?, display_order = ?, '
            . 'is_active = ?, is_public = ?, signup_available = ?, is_recommended = ?, is_legacy = ?, '
            . 'terms_summary = ?, updated_at = NOW() WHERE id = ?',
            [
                (string) $request->input('internal_name'),
                (string) $request->input('public_name'),
                (string) $request->input('description'),
                (int) round(((float) $request->input('monthly_price', 0)) * 100),
                (int) round(((float) $request->input('annual_price', 0)) * 100),
                (int) $request->input('trial_days', 0),
                (int) $request->input('display_order', 0),
                $request->input('is_active') ? 1 : 0,
                $request->input('is_public') ? 1 : 0,
                $request->input('signup_available') ? 1 : 0,
                $request->input('is_recommended') ? 1 : 0,
                $request->input('is_legacy') ? 1 : 0,
                (string) $request->input('terms_summary'),
                $planId,
            ]
        );

        foreach (Entitlements::limits() as $limitKey) {
            $raw = trim((string) $request->input('limit_' . $limitKey, ''));
            $value = ($raw === '' || strtolower($raw) === 'unlimited') ? null : (int) $raw;
            Database::query(
                'INSERT INTO billing_plan_limits (plan_id, limit_key, limit_value, created_at) VALUES (?, ?, ?, NOW()) '
                . 'ON DUPLICATE KEY UPDATE limit_value = VALUES(limit_value)',
                [$planId, $limitKey, $value]
            );
        }

        foreach (Entitlements::features() as $featureKey) {
            $enabled = $request->input('feature_' . $featureKey) ? 1 : 0;
            Database::query(
                'INSERT INTO billing_plan_features (plan_id, feature_key, is_enabled, created_at) VALUES (?, ?, ?, NOW()) '
                . 'ON DUPLICATE KEY UPDATE is_enabled = VALUES(is_enabled)',
                [$planId, $featureKey, $enabled]
            );
        }

        AuditLog::record('billing.plan_updated', 'plan', (string) $planId, null, (string) $request->input('public_name'));

        return $this->redirectWith('/admin/billing', 'success', 'Plan updated. Entitlement snapshots refresh as providers are re-evaluated.');
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,mixed>
     */
    private function mapRows(array $rows, string $keyCol, string $valueCol): array
    {
        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row[$keyCol]] = $row[$valueCol];
        }
        return $map;
    }
}
