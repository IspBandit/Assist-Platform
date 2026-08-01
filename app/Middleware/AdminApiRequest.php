<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Platform\Support\RequestContext;
use RuntimeException;

/**
 * Ensures Admin API responses carry correlation headers and reject non-JSON
 * bodies on mutating requests when a body is present.
 */
final class AdminApiRequest implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        if (!RequestContext::hasRequestId()) {
            RequestContext::begin($request);
        }

        $method = strtoupper($request->method());
        if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            $contentLength = (int) $request->server('CONTENT_LENGTH', 0);
            $contentType = strtolower((string) $request->header('Content-Type', ''));
            if ($contentLength > 0 && !str_contains($contentType, 'application/json')) {
                throw new \App\Core\Exceptions\AdminApiException(
                    415,
                    'unsupported_media_type',
                    'Admin API write requests must use Content-Type: application/json.'
                );
            }
        }

        $response = $next($request);
        if (!$response instanceof Response) {
            throw new RuntimeException('Admin API handlers must return a Response');
        }

        return $response
            ->withHeader('X-Request-ID', RequestContext::requestId())
            ->withHeader('Cache-Control', 'private, no-store, max-age=0');
    }
}
