<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\AdminApiException;
use App\Core\Middleware;
use App\Core\Request;

/**
 * Increment 1 placeholder: protected Admin API routes require a Bearer token.
 * Token verification ships in Increment 2; missing/invalid format still fails closed.
 */
final class RequireAdminApiBearerPlaceholder implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $header = trim((string) $request->header('Authorization', ''));
        if ($header === '' || !preg_match('/^Bearer\s+\S+/i', $header)) {
            throw new AdminApiException(
                401,
                'unauthenticated',
                'Bearer access token required. Authentication endpoints ship in Increment 2.'
            );
        }

        // Present but not yet verified — refuse rather than pretend success.
        throw new AdminApiException(
            401,
            'unauthenticated',
            'Admin API authentication is not yet enabled. Token verification arrives in Increment 2.'
        );
    }
}
