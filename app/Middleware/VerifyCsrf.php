<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Session;

/**
 * Validates the CSRF token on all state-changing requests.
 */
final class VerifyCsrf implements Middleware
{
    private const READ_METHODS = ['GET', 'HEAD', 'OPTIONS'];

    public function handle(Request $request, callable $next): mixed
    {
        if (in_array($request->method(), self::READ_METHODS, true)) {
            return $next($request);
        }

        if (!Session::verifyCsrf($request->csrfToken())) {
            throw new HttpException(419, 'Your session has expired. Please refresh and try again.');
        }

        return $next($request);
    }
}
