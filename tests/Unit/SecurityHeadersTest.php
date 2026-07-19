<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Core\Request;
use App\Core\Response;
use App\Middleware\SecurityHeaders;
use PHPUnit\Framework\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function testHeadersAreAppliedToResponseValue(): void
    {
        Config::set('security.headers', [
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ]);
        Config::set('security.csp', "default-src 'self'");
        Config::set('security.session.secure', false);

        $request = new Request([], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/',
        ], []);

        $response = (new SecurityHeaders())->handle(
            $request,
            static fn (Request $request): Response => Response::html('ok')
        );

        self::assertInstanceOf(Response::class, $response);
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options']);
        self::assertSame("default-src 'self'", $response->headers()['Content-Security-Policy']);
    }

    public function testForwardedHttpsIsTrustedOnlyFromConfiguredProxy(): void
    {
        Config::set('security.headers', []);
        Config::set('security.csp', '');
        Config::set('security.session.secure', true);
        Config::set('security.trusted_proxies', ['10.0.0.10']);
        $middleware = new SecurityHeaders();

        $trusted = $middleware->handle(
            new Request([], [], [
                'REMOTE_ADDR' => '10.0.0.10',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ], []),
            static fn (): Response => Response::html('ok')
        );
        $untrusted = $middleware->handle(
            new Request([], [], [
                'REMOTE_ADDR' => '198.51.100.20',
                'HTTP_X_FORWARDED_PROTO' => 'https',
            ], []),
            static fn (): Response => Response::html('ok')
        );

        self::assertArrayHasKey('Strict-Transport-Security', $trusted->headers());
        self::assertArrayNotHasKey('Strict-Transport-Security', $untrusted->headers());
    }

    public function testAuthenticatedResponsesAreNeverSharedCached(): void
    {
        Config::set('security.headers', []);
        Config::set('security.csp', '');
        Config::set('security.session.secure', false);
        $_SESSION['_auth_user_id'] = 123;

        try {
            $response = (new SecurityHeaders())->handle(
                new Request([], [], [], []),
                static fn (): Response => Response::html('private')
            );
            self::assertSame('private, no-store, max-age=0', $response->headers()['Cache-Control']);
            self::assertSame('no-cache', $response->headers()['Pragma']);
        } finally {
            unset($_SESSION['_auth_user_id']);
        }
    }
}
