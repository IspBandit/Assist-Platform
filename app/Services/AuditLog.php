<?php

declare(strict_types=1);

namespace App\Services;

use App\Auth\Auth;
use App\Core\Database;
use Throwable;

/**
 * Writes immutable audit records. Audit logs are never editable through the
 * normal interface.
 */
final class AuditLog
{
    public static function record(
        string $action,
        ?string $objectType = null,
        ?string $objectId = null,
        ?string $previous = null,
        ?string $new = null
    ): void {
        try {
            Database::query(
                'INSERT INTO audit_logs (user_id, action, object_type, object_id, previous_value, new_value, ip_address, user_agent, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    Auth::instance()->id(),
                    $action,
                    $objectType,
                    $objectId,
                    $previous,
                    $new,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
                ]
            );
        } catch (Throwable) {
            // Audit logging must never break the request.
        }
    }
}
