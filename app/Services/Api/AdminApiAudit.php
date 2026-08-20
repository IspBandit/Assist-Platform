<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Database;
use App\Core\Request;
use App\Platform\Support\RequestContext;
use Throwable;

/**
 * Audit helper for Admin API actors (human session or service account).
 */
final class AdminApiAudit
{
    public static function record(
        string $action,
        string $objectType,
        string|int|null $objectId,
        ?array $previous = null,
        ?array $next = null,
        ?Request $request = null
    ): void {
        try {
            $userId = AdminApiContext::userId();
            $meta = [
                'actor_type' => AdminApiContext::actorType(),
                'client_id' => AdminApiContext::clientId(),
                'access_token_id' => AdminApiContext::accessTokenId(),
                'request_id' => RequestContext::hasRequestId() ? RequestContext::requestId() : null,
            ];
            $previousPayload = $previous !== null
                ? json_encode(['fields' => $previous, 'meta' => $meta], JSON_THROW_ON_ERROR)
                : json_encode(['meta' => $meta], JSON_THROW_ON_ERROR);
            $nextPayload = $next !== null
                ? json_encode(['fields' => $next, 'meta' => $meta], JSON_THROW_ON_ERROR)
                : null;

            Database::query(
                'INSERT INTO audit_logs (user_id, action, object_type, object_id, previous_value, new_value, ip_address, user_agent, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [
                    $userId,
                    $action,
                    $objectType,
                    $objectId !== null ? (string) $objectId : null,
                    $previousPayload,
                    $nextPayload,
                    $request?->ip() ?? ($_SERVER['REMOTE_ADDR'] ?? null),
                    substr((string) ($request?->userAgent() ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')), 0, 500),
                ]
            );
        } catch (Throwable) {
            // Audit logging must never break the request.
        }
    }
}
