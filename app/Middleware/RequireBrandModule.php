<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;

/**
 * Fails closed when a route belongs to a module disabled for the trusted host.
 */
final class RequireBrandModule implements Middleware
{
    public function __construct(private readonly string $module)
    {
    }

    public function handle(Request $request, callable $next): mixed
    {
        if ($this->module === '' || !current_brand()->moduleEnabled($this->module)) {
            throw new HttpException(404, 'Page not found.');
        }

        return $next($request);
    }
}
