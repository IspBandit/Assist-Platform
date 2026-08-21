<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Demand\TrackingSession;
use Throwable;

/**
 * Privacy-friendly, first-party page-view recording. No third-party scripts and
 * no IP storage. A random first-party session provides de-duplicated visitor
 * counts; user identity is linked only when the visitor is already signed in.
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
            if (TrackingSession::isBot()) {
                return;
            }
            if (auth()->check() && auth()->hasAnyRole('super-administrator', 'administrator', 'platform-administrator', 'brand-administrator', 'moderator', 'marketing', 'support')) {
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

            $user = current_user();
            Database::query(
                'INSERT INTO page_views (brand_id, session_id, user_id, route, event_type, referrer_source, device_type, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [current_brand()->databaseId(), TrackingSession::id(), $user !== null ? (int) $user['id'] : null,
                    substr($path, 0, 190), 'view', self::referrerSource(), TrackingSession::deviceType()]
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
        foreach (current_brand()->domains() as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return 'internal';
            }
        }
        return substr($host, 0, 120);
    }
}
