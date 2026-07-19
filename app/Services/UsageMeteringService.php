<?php

declare(strict_types=1);

namespace App\Services;

use App\Billing\UsageCounters;
use App\Core\Database;

/**
 * Recalculates plan-controlled usage counters from authoritative tables and
 * stores them in provider_usage_counters. Usage is ALWAYS derived server-side;
 * browser-supplied counts are never trusted.
 *
 * Counters whose source tables only arrive in later phases simply resolve to 0
 * until those tables exist, so this is safe to run at any point.
 */
final class UsageMeteringService
{
    /** Recalculate every known counter for a provider and persist the results. */
    public function recalculateAll(int $providerId): void
    {
        foreach (UsageCounters::all() as $key) {
            $this->store($providerId, $key, $this->compute($providerId, $key));
        }
    }

    /** Recalculate a single counter and return its current value. */
    public function recalculate(int $providerId, string $counterKey): int
    {
        $value = $this->compute($providerId, $counterKey);
        $this->store($providerId, $counterKey, $value);
        return $value;
    }

    /** Read the stored value for a counter (recalculating if absent). */
    public function current(int $providerId, string $counterKey): int
    {
        $value = Database::scalar(
            'SELECT current_value FROM provider_usage_counters WHERE provider_id = ? AND counter_key = ?',
            [$providerId, $counterKey]
        );
        if ($value === null) {
            return $this->recalculate($providerId, $counterKey);
        }
        return (int) $value;
    }

    private function compute(int $providerId, string $counterKey): int
    {
        return match ($counterKey) {
            UsageCounters::ACTIVE_RUNS => $this->countIfTable(
                'service_runs',
                "SELECT COUNT(*) FROM service_runs WHERE provider_id = ? "
                . "AND status IN ('proposed','forming','confirmed','limited','fully_booked') "
                . "AND deleted_at IS NULL",
                [$providerId]
            ),
            UsageCounters::SERVICE_AREAS => $this->countIfTable(
                'provider_service_areas',
                'SELECT COUNT(*) FROM provider_service_areas WHERE provider_id = ?',
                [$providerId]
            ),
            UsageCounters::SERVICE_CATEGORIES => $this->countIfTable(
                'provider_services',
                'SELECT COUNT(*) FROM provider_services WHERE provider_id = ?',
                [$providerId]
            ),
            UsageCounters::PROVIDER_USERS => $this->countIfTable(
                'provider_users',
                'SELECT COUNT(*) FROM provider_users WHERE provider_id = ?',
                [$providerId]
            ),
            UsageCounters::BRANCHES => $this->countIfTable(
                'provider_branches',
                'SELECT COUNT(*) FROM provider_branches WHERE provider_id = ?',
                [$providerId]
            ),
            // Event-accumulated counters (responses, exports, notifications,
            // storage) are incremented as actions occur; default to stored value.
            default => $this->stored($providerId, $counterKey),
        };
    }

    /** Increment an accumulating counter (e.g. data exports, request responses). */
    public function increment(int $providerId, string $counterKey, int $by = 1): int
    {
        $value = $this->stored($providerId, $counterKey) + $by;
        $this->store($providerId, $counterKey, $value);
        return $value;
    }

    private function stored(int $providerId, string $counterKey): int
    {
        return (int) Database::scalar(
            'SELECT current_value FROM provider_usage_counters WHERE provider_id = ? AND counter_key = ?',
            [$providerId, $counterKey]
        );
    }

    private function store(int $providerId, string $counterKey, int $value): void
    {
        Database::query(
            'INSERT INTO provider_usage_counters (provider_id, counter_key, current_value, updated_at) '
            . 'VALUES (?, ?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE current_value = VALUES(current_value), updated_at = NOW()',
            [$providerId, $counterKey, $value]
        );
    }

    /** @param array<int,mixed> $params */
    private function countIfTable(string $table, string $sql, array $params): int
    {
        if (!Database::tableExists($table)) {
            return 0;
        }
        return (int) Database::scalar($sql, $params);
    }
}
