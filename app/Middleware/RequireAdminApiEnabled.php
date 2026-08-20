<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Exceptions\AdminApiException;
use App\Core\Middleware;
use App\Core\Request;

/**
 * Fail closed when ADMIN_API_ENABLED is false.
 */
final class RequireAdminApiEnabled implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!(bool) Config::get('admin_api.enabled', false)) {
            throw new AdminApiException(
                503,
                'api_disabled',
                'The Admin API is disabled on this deployment.'
            );
        }

        return $next($request);
    }
}
