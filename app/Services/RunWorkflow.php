<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Service-run status transitions and capacity bookkeeping. Every status change
 * is recorded in service_run_status_history.
 */
final class RunWorkflow
{
    public const LABELS = [
        'proposed'     => 'Proposed',
        'forming'      => 'Forming',
        'confirmed'    => 'Confirmed',
        'limited'      => 'Limited availability',
        'fully_booked' => 'Fully booked',
        'completed'    => 'Completed',
        'cancelled'    => 'Cancelled',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status ?? ''] ?? ucfirst((string) $status);
    }

    public static function changeStatus(int $runId, string $toStatus, ?int $changedBy, ?string $note = null): void
    {
        if (!array_key_exists($toStatus, self::LABELS)) {
            return;
        }
        $current = Database::selectOne('SELECT status FROM service_runs WHERE id = ?', [$runId]);
        $from = $current['status'] ?? null;
        Database::query('UPDATE service_runs SET status = ?, updated_at = NOW() WHERE id = ?', [$toStatus, $runId]);
        self::recordHistory($runId, $from, $toStatus, $changedBy, $note);
    }

    public static function recordHistory(int $runId, ?string $from, string $to, ?int $changedBy, ?string $note = null): void
    {
        Database::query(
            'INSERT INTO service_run_status_history (run_id, from_status, to_status, changed_by, note, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())',
            [$runId, $from, $to, $changedBy, $note]
        );
    }

    /**
     * Recalculate the live booking count from active bookings and auto-advance
     * the status to fully_booked when capacity is reached (and back to confirmed
     * when a spot frees up). Never overrides completed/cancelled runs.
     */
    public static function recalcCapacity(int $runId): void
    {
        $run = Database::selectOne('SELECT status, appointments_total FROM service_runs WHERE id = ?', [$runId]);
        if ($run === null) {
            return;
        }
        $count = (int) Database::scalar(
            "SELECT COUNT(*) FROM service_run_bookings WHERE run_id = ? AND status IN ('joined','confirmed','completed')",
            [$runId]
        );
        Database::query('UPDATE service_runs SET bookings_count = ?, updated_at = NOW() WHERE id = ?', [$count, $runId]);

        $total = (int) ($run['appointments_total'] ?? 0);
        if ($total <= 0 || in_array($run['status'], ['completed', 'cancelled'], true)) {
            return;
        }
        if ($count >= $total && $run['status'] !== 'fully_booked') {
            self::changeStatus($runId, 'fully_booked', null, 'Capacity reached');
        } elseif ($count < $total && $run['status'] === 'fully_booked') {
            self::changeStatus($runId, 'confirmed', null, 'A place became available');
        }
    }
}
