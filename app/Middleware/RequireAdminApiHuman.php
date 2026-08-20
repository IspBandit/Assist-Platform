<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\AdminApiException;
use App\Core\Middleware;
use App\Core\Request;
use App\Services\Api\AdminApiContext;

/**
 * Restricts Admin API routes to human bearer tokens (not service accounts).
 */
final class RequireAdminApiHuman implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!AdminApiContext::isHuman()) {
            throw new AdminApiException(
                403,
                'forbidden',
                'This endpoint requires human authentication.'
            );
        }

        return $next($request);
    }
}
