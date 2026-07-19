<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;

/**
 * Restricts a route to users with a specific permission slug.
 * Usage: 'permission:providers.approve'
 */
final class RequirePermission implements Middleware
{
    public function __construct(private string $permission)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if (!Auth::instance()->can($this->permission)) {
            throw new HttpException(403, 'You do not have permission to perform this action.');
        }
        return $next($request);
    }
}
