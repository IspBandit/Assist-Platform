<?php

declare(strict_types=1);

use App\Helpers\Env;

return [
    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) Env::get('TRUSTED_PROXIES', ''))
    ))),

    'session' => [
        'name'             => 'vanassist_session',
        'lifetime_minutes' => (int) Env::get('SESSION_LIFETIME', 120),
        'secure'           => (bool) Env::get('SESSION_SECURE', true),
        'http_only'        => true,
        'same_site'        => 'Lax',
    ],

    'login' => [
        'max_attempts'    => (int) Env::get('LOGIN_MAX_ATTEMPTS', 5),
        'lockout_minutes' => (int) Env::get('LOGIN_LOCKOUT_MINUTES', 15),
    ],

    'admin_session_timeout_minutes' => (int) Env::get('ADMIN_SESSION_TIMEOUT', 30),

    'password_reset_expiry_minutes' => 60,
    'email_verification_expiry_hours' => 48,
    'provider_invite_expiry_days'   => 14,

    'turnstile' => [
        'enabled'    => (bool) Env::get('TURNSTILE_ENABLED', false),
        'site_key'   => Env::get('TURNSTILE_SITE_KEY', ''),
        'secret_key' => Env::get('TURNSTILE_SECRET_KEY', ''),
    ],

    // Sent as HTTP response headers on every request (see SecurityHeaders middleware).
    'headers' => [
        'X-Frame-Options'        => 'SAMEORIGIN',
        'X-Content-Type-Options' => 'nosniff',
        'Referrer-Policy'        => 'strict-origin-when-cross-origin',
        'X-XSS-Protection'       => '0',
        'Permissions-Policy'     => 'geolocation=(self), camera=(), microphone=()',
    ],

    // A conservative CSP that allows self plus inline styles (used by lean pages).
    'csp' => "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; "
        . "script-src 'self'; font-src 'self' data:; connect-src 'self'; frame-ancestors 'self'; "
        . "base-uri 'self'; form-action 'self'",
];
