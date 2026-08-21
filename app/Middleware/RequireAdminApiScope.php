<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\AdminApiException;
use App\Core\Middleware;
use App\Core\Request;
use App\Services\Api\AdminApiContext;

/**
 * Requires the bearer actor to hold at least one of the given scopes.
 * Usage: 'admin_api_scope:providers:read,providers:write'
 */
final class RequireAdminApiScope implements Middleware
{
    /** @var list<string> */
    private array $scopes;

    public function __construct(string ...$scopes)
    {
        $this->scopes = $scopes;
    }

    public function handle(Request $request, callable $next): mixed
    {
        if ($this->scopes === []) {
            return $next($request);
        }

        if (!AdminApiContext::hasAnyScope(...$this->scopes)) {
            throw new AdminApiException(
                403,
                'forbidden',
                'Insufficient scope for this endpoint.'
            );
        }

        return $next($request);
    }
}
