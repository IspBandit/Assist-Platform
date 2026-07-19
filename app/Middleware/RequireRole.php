<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth\Auth;
use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;

/**
 * Restricts a route to users holding at least one of the given role slugs.
 * Usage: 'role:administrator,super-administrator'
 */
final class RequireRole implements Middleware
{
    /** @var array<int,string> */
    private array $roles;

    public function __construct(string ...$roles)
    {
        $this->roles = $roles;
    }

    public function handle(Request $request, callable $next): mixed
    {
        $auth = Auth::instance();
        if ($auth->guest()) {
            throw new HttpException(403, 'Authentication required.');
        }
        if (!$auth->hasAnyRole(...$this->roles)) {
            throw new HttpException(403, 'You do not have access to this area.');
        }
        return $next($request);
    }
}
