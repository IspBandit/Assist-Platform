<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use Throwable;

/**
 * Privacy-friendly, first-party page-view recording. No cookies, no third-party
 * scripts (so it never conflicts with the site CSP) and no IP storage — only the
 * route and a coarse referrer source. Off unless the analytics_enabled setting
 * is on, so there is zero overhead by default.
 */
final class Analytics
{
    private const SKIP_PREFIXES = ['/admin', '/install', '/account', '/provider', '/park', '/billing', '/assets', '/uploads'];

    public static function record(Request $request, Response $response): void
    {
        try {
            if (PHP_SAPI === 'cli' || $request->method() !== 'GET' || $response->status() !== 200) {
                return;
            }
            if ((string) Settings::get('analytics_enabled', '0') !== '1') {
                return;
            }

            $path = '/' . ltrim($request->path(), '/');
            foreach (self::SKIP_PREFIXES as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                    return;
                }
            }
            if (in_array($path, ['/sitemap.xml', '/robots.txt', '/favicon.ico'], true)) {
                return;
            }

            Database::query(
                'INSERT INTO page_views (route, event_type, referrer_source, created_at) VALUES (?, ?, ?, NOW())',
                [substr($path, 0, 190), 'view', self::referrerSource()]
            );
        } catch (Throwable) {
            // Analytics must never affect the response.
        }
    }

    private static function referrerSource(): ?string
    {
        $ref = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($ref === '') {
            return 'direct';
        }
        $host = parse_url($ref, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return 'direct';
        }
        $self = parse_url((string) config('app.url', ''), PHP_URL_HOST);
        if (is_string($self) && $self !== '' && str_ends_with($host, $self)) {
            return 'internal';
        }
        return substr($host, 0, 120);
    }
}
