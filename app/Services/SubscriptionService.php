<?php

declare(strict_types=1);

namespace App\Services;

use App\Billing\Entitlements;
use App\Billing\MembershipCatalogue;
use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * Manages provider subscriptions, plan assignment and the entitlement snapshot.
 *
 * Two principles drive this service:
 *  - Records are never destroyed because a subscription ends. Downgrades fall
 *    back to a configurable free plan; profiles, runs, requests, documents and
 *    founding benefits are preserved.
 *  - Founding benefits are snapshotted against the provider so a later edit to
 *    a shared plan cannot silently revoke what was agreed.
 *
 * During the free launch every provider is provisioned as "complimentary" with
 * billing_required = false, so nothing is charged or blocked.
 */
final class SubscriptionService
{
    public function __construct(
        private readonly UsageMeteringService $usage = new UsageMeteringService()
    ) {
    }

    /**
     * Give a provider all the billing-side records they need, even during the
     * free launch: an assigned plan, a subscription row, an entitlement
     * snapshot, usage counters and a billing-customer placeholder.
     *
     * @param array{founding?:bool,plan_slug?:string,founding_benefits?:array<string,mixed>} $options
     */
    public function provisionProvider(int $providerId, array $options = []): void
    {
        if (!self::billingSchemaReady()) {
            Logger::info('Billing provisioning skipped (billing migration not applied yet).', [
                'provider_id' => $providerId,
            ], 'app');
            return;
        }

        try {
            $founding = (bool) ($options['founding'] ?? false);
            $planSlug = $options['plan_slug']
                ?? ($founding ? MembershipCatalogue::FOUNDING_VERIFIED : (string) config('billing.default_free_plan', MembershipCatalogue::FREE_LISTING));

            $planId = $this->planIdBySlug($planSlug) ?? $this->planIdBySlug((string) config('billing.default_free_plan', MembershipCatalogue::FREE_LISTING));

            $state = 'complimentary';

            Database::query(
                'UPDATE providers SET plan_id = ?, subscription_state = ?, billing_required = 0, updated_at = NOW() WHERE id = ?',
                [$planId, $state, $providerId]
            );

            if ($founding) {
                $this->markFounding($providerId, $planId, $options['founding_benefits'] ?? []);
            }

            $this->ensureSubscriptionRow($providerId, $planId, $state);
            $this->ensureBillingCustomer($providerId);
            $this->usage->recalculateAll($providerId);
            $this->snapshotEntitlements($providerId);
        } catch (Throwable $e) {
            // Provider accounts must work even when billing tables/plans are not ready.
            Logger::error('Billing provisioning failed for provider #' . $providerId . ': ' . $e->getMessage(), [
                'provider_id' => $providerId,
                'exception' => get_class($e),
            ], 'errors');
        }
    }

    /** Whether migration 012 (billing) has been applied. */
    public static function billingSchemaReady(): bool
    {
        static $ready = null;
        if ($ready !== null) {
            return $ready;
        }

        if (!Database::tableExists('billing_plans')
            || !Database::tableExists('provider_subscriptions')
            || !Database::tableExists('provider_entitlements')
        ) {
            return $ready = false;
        }

        $hasPlanColumn = (int) Database::scalar(
            'SELECT COUNT(*) FROM information_schema.columns '
            . "WHERE table_schema = DATABASE() AND table_name = 'providers' AND column_name = 'plan_id'"
        );

        return $ready = ($hasPlanColumn > 0);
    }

    /**
     * Record the agreed founding-provider entitlement snapshot directly on the
     * provider so it cannot be lost by later plan edits.
     *
     * @param array<string,mixed> $benefits
     */
    public function markFounding(int $providerId, ?int $foundingPlanId = null, array $benefits = []): void
    {
        Database::query(
            'UPDATE providers SET '
            . 'is_founding_provider = 1, '
            . 'founding_provider_joined_at = COALESCE(founding_provider_joined_at, NOW()), '
            . 'founding_plan_id = ?, '
            . 'founding_benefits_json = ?, '
            . 'founding_lifetime_standard_access = ?, '
            . 'founding_terms_version = COALESCE(founding_terms_version, ?), '
            . 'updated_at = NOW() '
            . 'WHERE id = ?',
            [
                $foundingPlanId,
                $benefits === [] ? null : json_encode($benefits),
                (int) ($benefits['lifetime_standard_access'] ?? 1),
                (string) ($benefits['terms_version'] ?? 'founding-v1'),
                $providerId,
            ]
        );
        AuditLog::record('billing.founding_marked', 'provider', (string) $providerId, null, json_encode($benefits) ?: null);
        $this->snapshotEntitlements($providerId);
    }

    /** Assign a provider to a plan (single). */
    public function assignPlan(int $providerId, int $planId, string $reason = '', string $interval = 'none'): void
    {
        $previous = $this->providerState($providerId);
        Database::query(
            'UPDATE providers SET plan_id = ?, updated_at = NOW() WHERE id = ?',
            [$planId, $providerId]
        );
        Database::query(
            'UPDATE provider_subscriptions SET plan_id = ?, billing_interval = ?, updated_at = NOW() WHERE provider_id = ?',
            [$planId, $interval, $providerId]
        );
        $this->recordHistory($providerId, $previous['subscription_state'] ?? null, $previous['subscription_state'] ?? 'complimentary', $previous['plan_id'] ?? null, $planId, $reason);
        $this->snapshotEntitlements($providerId);
        AuditLog::record('billing.plan_assigned', 'provider', (string) $providerId, (string) ($previous['plan_id'] ?? ''), (string) $planId);
    }

    /**
     * Bulk-migrate providers between plans.
     * @param array<int,int> $providerIds
     */
    public function bulkAssignPlan(array $providerIds, int $planId, string $reason = 'bulk migration'): int
    {
        $count = 0;
        foreach ($providerIds as $providerId) {
            $this->assignPlan((int) $providerId, $planId, $reason);
            $count++;
        }
        AuditLog::record('billing.bulk_plan_assigned', 'plan', (string) $planId, null, (string) $count);
        return $count;
    }

    /** Grandfather a provider: keep their current plan and protect it as a snapshot. */
    public function grandfather(int $providerId, string $reason = 'grandfathered'): void
    {
        $state = $this->providerState($providerId);
        $planId = isset($state['plan_id']) ? (int) $state['plan_id'] : null;
        $this->setSubscriptionState($providerId, 'manually_managed', $reason);
        $this->markFounding($providerId, $planId, ['grandfathered' => true, 'reason' => $reason]);
        AuditLog::record('billing.grandfathered', 'provider', (string) $providerId, null, $reason);
    }

    /** Grant complimentary (free, admin-managed) access without payment. */
    public function grantComplimentary(int $providerId, string $reason = 'complimentary access'): void
    {
        Database::query(
            'UPDATE providers SET subscription_state = ?, billing_required = 0, updated_at = NOW() WHERE id = ?',
            ['complimentary', $providerId]
        );
        $this->setSubscriptionState($providerId, 'complimentary', $reason);
        AuditLog::record('billing.complimentary_granted', 'provider', (string) $providerId, null, $reason);
    }

    /** Apply an admin per-provider entitlement/limit/price override. */
    public function applyOverride(int $providerId, string $type, string $key, ?string $value, ?string $expiresAt = null, ?int $adminId = null): void
    {
        Database::query(
            'INSERT INTO provider_plan_overrides (provider_id, override_type, override_key, override_value, expires_at, created_by, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$providerId, $type, $key, $value, $expiresAt, $adminId]
        );
        $this->snapshotEntitlements($providerId);
        AuditLog::record('billing.override_applied', 'provider', (string) $providerId, $key, (string) $value);
    }

    /** Transition the subscription state, recording history and audit. */
    public function setSubscriptionState(int $providerId, string $toState, string $reason = ''): void
    {
        $previous = $this->providerState($providerId);
        $from = $previous['subscription_state'] ?? null;
        Database::query(
            'UPDATE providers SET subscription_state = ?, updated_at = NOW() WHERE id = ?',
            [$toState, $providerId]
        );
        Database::query(
            'UPDATE provider_subscriptions SET status = ?, updated_at = NOW() WHERE provider_id = ?',
            [$toState, $providerId]
        );
        $this->recordHistory($providerId, $from, $toState, $previous['plan_id'] ?? null, $previous['plan_id'] ?? null, $reason);
        AuditLog::record('billing.state_changed', 'provider', (string) $providerId, (string) $from, $toState);
    }

    /**
     * Recompute provider_entitlements from the plan, then apply the founding
     * snapshot (founding wins, so agreed benefits survive plan edits).
     */
    public function snapshotEntitlements(int $providerId): void
    {
        $state = $this->providerState($providerId);
        $planId = isset($state['plan_id']) ? (int) $state['plan_id'] : 0;

        $resolved = [];

        if ($planId > 0) {
            foreach (Database::select('SELECT feature_key, is_enabled FROM billing_plan_features WHERE plan_id = ?', [$planId]) as $row) {
                $resolved[(string) $row['feature_key']] = [(int) $row['is_enabled'] === 1 ? '1' : '0', 'plan'];
            }
            foreach (Database::select('SELECT limit_key, limit_value FROM billing_plan_limits WHERE plan_id = ?', [$planId]) as $row) {
                $resolved[(string) $row['limit_key']] = [$row['limit_value'] === null ? 'unlimited' : (string) $row['limit_value'], 'plan'];
            }
        }

        // Founding snapshot overrides plan values where specified.
        $founding = $state['founding_benefits_json'] ?? null;
        if (!empty($state['is_founding_provider']) && $founding) {
            $benefits = json_decode((string) $founding, true);
            if (is_array($benefits)) {
                foreach (($benefits['entitlements'] ?? []) as $key => $value) {
                    $resolved[(string) $key] = [is_bool($value) ? ($value ? '1' : '0') : (string) $value, 'founding'];
                }
                foreach (($benefits['limits'] ?? []) as $key => $value) {
                    $resolved[(string) $key] = [$value === null ? 'unlimited' : (string) $value, 'founding'];
                }
            }
        }

        foreach ($resolved as $key => [$value, $source]) {
            Database::query(
                'INSERT INTO provider_entitlements (provider_id, entitlement_key, entitlement_value, source, updated_at) '
                . 'VALUES (?, ?, ?, ?, NOW()) '
                . 'ON DUPLICATE KEY UPDATE entitlement_value = VALUES(entitlement_value), source = VALUES(source), updated_at = NOW()',
                [$providerId, $key, $value, $source]
            );
        }
    }

    // --- internals ----------------------------------------------------------

    private function ensureSubscriptionRow(int $providerId, ?int $planId, string $state): void
    {
        $exists = (int) Database::scalar('SELECT COUNT(*) FROM provider_subscriptions WHERE provider_id = ?', [$providerId]);
        if ($exists === 0) {
            Database::query(
                'INSERT INTO provider_subscriptions (provider_id, plan_id, status, billing_interval, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, NOW(), NOW())',
                [$providerId, $planId, $state, 'none']
            );
        }
    }

    private function ensureBillingCustomer(int $providerId): void
    {
        $exists = (int) Database::scalar('SELECT COUNT(*) FROM billing_customers WHERE provider_id = ?', [$providerId]);
        if ($exists === 0) {
            $row = Database::selectOne('SELECT business_name, email, abn FROM providers WHERE id = ?', [$providerId]);
            Database::query(
                'INSERT INTO billing_customers (provider_id, business_name, billing_email, abn, created_at, updated_at) '
                . 'VALUES (?, ?, ?, ?, NOW(), NOW())',
                [$providerId, $row['business_name'] ?? null, $row['email'] ?? null, $row['abn'] ?? null]
            );
        }
    }

    private function recordHistory(int $providerId, ?string $from, string $to, ?int $fromPlan, ?int $toPlan, string $reason): void
    {
        Database::query(
            'INSERT INTO provider_subscription_history (provider_id, from_status, to_status, from_plan_id, to_plan_id, reason, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [$providerId, $from, $to, $fromPlan, $toPlan, $reason !== '' ? $reason : null]
        );
    }

    /** @return array<string,mixed> */
    private function providerState(int $providerId): array
    {
        return Database::selectOne(
            'SELECT plan_id, subscription_state, is_founding_provider, founding_benefits_json FROM providers WHERE id = ?',
            [$providerId]
        ) ?? [];
    }

    private function planIdBySlug(string $slug): ?int
    {
        $id = Database::scalar('SELECT id FROM billing_plans WHERE slug = ?', [$slug]);
        return $id === null || $id === false ? null : (int) $id;
    }
}
