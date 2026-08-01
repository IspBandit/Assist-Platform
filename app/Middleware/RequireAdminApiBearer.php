<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\AdminApiException;
use App\Core\Middleware;
use App\Core\Request;
use App\Services\Api\AdminApiAuthService;
use App\Services\Api\AdminApiContext;

/**
 * Verifies Bearer access tokens for protected Admin API routes.
 */
final class RequireAdminApiBearer implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        AdminApiContext::clear();

        $header = trim((string) $request->header('Authorization', ''));
        if ($header === '' || !preg_match('/^Bearer\s+(\S+)/i', $header, $matches)) {
            throw new AdminApiException(
                401,
                'unauthenticated',
                'Bearer access token required.'
            );
        }

        $authenticated = (new AdminApiAuthService())->authenticateAccessToken($matches[1], $request);
        if (!$authenticated) {
            throw new AdminApiException(
                401,
                'unauthenticated',
                'Access token is invalid or expired.'
            );
        }

        try {
            return $next($request);
        } finally {
            AdminApiContext::clear();
        }
    }
}
