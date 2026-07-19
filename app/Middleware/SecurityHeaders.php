<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Platform\Support\RequestContext;
use RuntimeException;

/**
 * Emits hardening HTTP headers on every response.
 */
final class SecurityHeaders implements Middleware
{
    public function handle(Request $request, callable $next): mixed
    {
        $response = $next($request);
        if (!$response instanceof Response) {
            throw new RuntimeException('SecurityHeaders must wrap a normalized Response');
        }

        foreach ((array) Config::get('security.headers', []) as $name => $value) {
            if (is_string($name) && is_scalar($value)) {
                $response = $response->withHeader($name, (string) $value);
            }
        }

        $csp = (string) Config::get('security.csp', '');
        if ($csp !== '') {
            $response = $response->withHeader('Content-Security-Policy', $csp);
        }

        // Enforce HTTPS for clients once served over TLS.
        if ((bool) Config::get('security.session.secure', true) && $this->isHttps($request)) {
            $response = $response->withHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        if (Session::has('_auth_user_id')) {
            $response = $response
                ->withHeader('Cache-Control', 'private, no-store, max-age=0')
                ->withHeader('Pragma', 'no-cache');
        }
        if (RequestContext::hasRequestId()) {
            $response = $response->withHeader('X-Request-ID', RequestContext::requestId());
        }

        return $response;
    }

    private function isHttps(Request $request): bool
    {
        if ((string) $request->server('HTTPS', '') === 'on'
            || (string) $request->server('SERVER_PORT', '') === '443') {
            return true;
        }

        $trusted = (array) Config::get('security.trusted_proxies', []);
        return in_array($request->remoteIp(), $trusted, true)
            && strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';
    }
}
