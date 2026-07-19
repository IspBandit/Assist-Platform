<?php

declare(strict_types=1);

namespace App\Services;

use App\Billing\BillingManager;
use App\Billing\Entitlements;
use App\Core\Database;

/**
 * The single source of truth for "may this provider use feature X / are they
 * within limit Y?". Controllers call this service rather than scattering plan
 * checks, so plans and limits can change in the database without code edits.
 *
 * While ENABLE_BILLING=false the service is fully permissive: every feature is
 * allowed and every limit is unlimited, so no provider capability is gated by
 * payment status during the free launch.
 *
 * When billing is on, resolution order for a key is:
 *   1. active per-provider override (provider_plan_overrides)
 *   2. snapshot in provider_entitlements (includes founding snapshot)
 *   3. the provider's plan (billing_plan_features / billing_plan_limits)
 *   4. safe default (feature: deny, limit: 0)
 */
final class PlanEntitlementService
{
    public function __construct(
        private readonly UsageMeteringService $usage = new UsageMeteringService()
    ) {
    }

    /** True when paid gating is active. Mirrors the master billing switch. */
    public function gatingEnabled(): bool
    {
        return BillingManager::enabled();
    }

    /** Whether a provider may use a boolean feature entitlement. */
    public function can(int $providerId, string $featureKey): bool
    {
        if (!$this->gatingEnabled()) {
            return true;
        }

        $override = $this->override($providerId, $featureKey, 'entitlement');
        if ($override !== null) {
            return $this->truthy($override);
        }

        $snapshot = $this->snapshotValue($providerId, $featureKey);
        if ($snapshot !== null) {
            return $this->truthy($snapshot);
        }

        $planValue = $this->planFeature($providerId, $featureKey);
        if ($planValue !== null) {
            return $planValue;
        }

        return false;
    }

    /**
     * Resolve a numeric limit. Returns null for "unlimited", otherwise the cap.
     * While billing is disabled everything is unlimited.
     */
    public function limit(int $providerId, string $limitKey): ?int
    {
        if (!$this->gatingEnabled()) {
            return null;
        }

        $override = $this->override($providerId, $limitKey, 'limit');
        if ($override !== null) {
            return $this->normaliseLimit($override);
        }

        $snapshot = $this->snapshotValue($providerId, $limitKey);
        if ($snapshot !== null) {
            return $this->normaliseLimit($snapshot);
        }

        return $this->planLimit($providerId, $limitKey);
    }

    /**
     * Whether the provider is still within a limit for the matching usage
     * counter. Usage is recalculated server-side. Unlimited always passes.
     */
    public function withinLimit(int $providerId, string $limitKey): bool
    {
        $cap = $this->limit($providerId, $limitKey);
        if ($cap === null) {
            return true;
        }

        $counterKey = Entitlements::limitCounterMap()[$limitKey] ?? null;
        if ($counterKey === null) {
            return true;
        }

        return $this->usage->recalculate($providerId, $counterKey) < $cap;
    }

    /** Remaining capacity for a limit (null = unlimited). */
    public function remaining(int $providerId, string $limitKey): ?int
    {
        $cap = $this->limit($providerId, $limitKey);
        if ($cap === null) {
            return null;
        }
        $counterKey = Entitlements::limitCounterMap()[$limitKey] ?? null;
        $used = $counterKey ? $this->usage->current($providerId, $counterKey) : 0;
        return max(0, $cap - $used);
    }

    // --- resolution helpers -------------------------------------------------

    private function override(int $providerId, string $key, string $type): ?string
    {
        $value = Database::scalar(
            'SELECT override_value FROM provider_plan_overrides '
            . 'WHERE provider_id = ? AND override_type = ? AND override_key = ? '
            . 'AND (expires_at IS NULL OR expires_at > NOW()) '
            . 'ORDER BY id DESC LIMIT 1',
            [$providerId, $type, $key]
        );
        return $value === null || $value === false ? null : (string) $value;
    }

    private function snapshotValue(int $providerId, string $key): ?string
    {
        $value = Database::scalar(
            'SELECT entitlement_value FROM provider_entitlements WHERE provider_id = ? AND entitlement_key = ?',
            [$providerId, $key]
        );
        return $value === null || $value === false ? null : (string) $value;
    }

    private function planFeature(int $providerId, string $featureKey): ?bool
    {
        $value = Database::scalar(
            'SELECT bpf.is_enabled FROM providers p '
            . 'JOIN billing_plan_features bpf ON bpf.plan_id = p.plan_id '
            . 'WHERE p.id = ? AND bpf.feature_key = ?',
            [$providerId, $featureKey]
        );
        return $value === null || $value === false ? null : ((int) $value === 1);
    }

    private function planLimit(int $providerId, string $limitKey): ?int
    {
        $row = Database::selectOne(
            'SELECT bpl.limit_value FROM providers p '
            . 'JOIN billing_plan_limits bpl ON bpl.plan_id = p.plan_id '
            . 'WHERE p.id = ? AND bpl.limit_key = ?',
            [$providerId, $limitKey]
        );
        if ($row === null) {
            return 0; // no plan / limit defined => most restrictive
        }
        return $row['limit_value'] === null ? null : (int) $row['limit_value'];
    }

    private function normaliseLimit(string $value): ?int
    {
        $lower = strtolower(trim($value));
        if ($lower === '' || $lower === 'null' || $lower === 'unlimited') {
            return null;
        }
        return (int) $value;
    }

    private function truthy(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }
}
