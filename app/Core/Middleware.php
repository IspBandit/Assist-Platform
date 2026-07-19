<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Contract for HTTP middleware. Implementations receive the request and a
 * $next callable; they either return a Response (short-circuit) or call
 * $next($request) to continue down the pipeline.
 */
interface Middleware
{
    public function handle(Request $request, callable $next): mixed;
}
