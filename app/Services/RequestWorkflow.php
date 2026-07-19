<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Centralises service-request status transitions so every change is recorded
 * in the immutable status-history table with a consistent audit trail.
 */
final class RequestWorkflow
{
    /** Human-readable labels for each lifecycle status. */
    public const LABELS = [
        'draft'                 => 'Draft',
        'awaiting_verification' => 'Awaiting email verification',
        'pending_moderation'    => 'Pending review',
        'open'                  => 'Open',
        'matching'              => 'Matching',
        'provider_interested'   => 'Provider interested',
        'information_requested' => 'Information requested',
        'offered_appointment'   => 'Appointment offered',
        'added_to_run'          => 'Added to a run',
        'accepted'              => 'Accepted',
        'in_progress'           => 'In progress',
        'completed'             => 'Completed',
        'closed'                => 'Closed',
        'cancelled'             => 'Cancelled',
        'rejected'              => 'Rejected',
        'expired'               => 'Expired',
    ];

    public static function label(?string $status): string
    {
        return self::LABELS[$status ?? ''] ?? ucfirst((string) $status);
    }

    /** Record a status change and update the request. */
    public static function changeStatus(int $requestId, string $toStatus, ?int $changedBy, ?string $note = null): void
    {
        $current = Database::selectOne('SELECT status FROM service_requests WHERE id = ?', [$requestId]);
        $from = $current['status'] ?? null;
        if ($from === $toStatus && $note === null) {
            return;
        }

        Database::query(
            'UPDATE service_requests SET status = ?, updated_at = NOW() WHERE id = ?',
            [$toStatus, $requestId]
        );
        self::recordHistory($requestId, $from, $toStatus, $changedBy, $note);
    }

    public static function recordHistory(int $requestId, ?string $from, string $to, ?int $changedBy, ?string $note = null): void
    {
        Database::query(
            'INSERT INTO service_request_status_history (request_id, from_status, to_status, changed_by, note, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())',
            [$requestId, $from, $to, $changedBy, $note]
        );
    }
}
