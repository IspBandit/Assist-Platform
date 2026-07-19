<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Auth\Auth;
use App\Core\Database;
use Throwable;

/**
 * Append-only finance audit log (separate from the platform audit_logs table so
 * finance review can be scoped and exported independently). Never throws.
 */
final class FinanceAudit
{
    public static function record(
        string $action,
        ?string $entityType = null,
        ?string $entityId = null,
        ?array $before = null,
        ?array $after = null,
        ?string $reason = null
    ): void {
        try {
            Database::query(
                'INSERT INTO owner_finance_audit_events '
                . '(user_id, action, entity_type, entity_id, before_json, after_json, reason, ip_address, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    Auth::instance()->id(),
                    $action,
                    $entityType,
                    $entityId,
                    $before !== null ? json_encode($before, JSON_UNESCAPED_SLASHES) : null,
                    $after !== null ? json_encode($after, JSON_UNESCAPED_SLASHES) : null,
                    $reason,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (Throwable) {
            // Auditing must never break a finance request.
        }
    }

    /** @return array<int,array<string,mixed>> */
    public static function recent(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        return Database::select(
            'SELECT a.*, u.name AS user_name FROM owner_finance_audit_events a '
            . 'LEFT JOIN users u ON u.id = a.user_id '
            . "ORDER BY a.id DESC LIMIT {$limit}"
        );
    }
}
