<?php

declare(strict_types=1);

namespace App\Services\Demand;

use App\Auth\Auth;
use App\Core\Database;
use Throwable;

/**
 * First-party, privacy-conscious visitor identity for the demand funnel.
 *
 * - A random, non-personal token is stored in a first-party cookie (va_sid)
 *   and backed by a tracking_sessions row. No third-party scripts, no IP
 *   storage (the user-agent is only kept as a salted hash for bot/dedupe
 *   heuristics), and never used for cross-site advertising.
 * - When an anonymous visitor signs in, their existing session row is linked
 *   to the user/customer so prior activity can be safely associated.
 * - Every method is defensive: a tracking failure must never affect the
 *   customer's ability to use the site, so all errors are swallowed.
 *
 * Resolution is cached per request. Nothing here runs unless the
 * demand_analytics feature flag is on (callers gate via ActivityTracker).
 */
final class TrackingSession
{
    public const COOKIE = 'va_sid';
    private const LIFETIME_DAYS = 180;

    private static ?int $sessionId = null;
    private static bool $resolved = false;

    /**
     * Return the current tracking_sessions.id, creating the session row and
     * issuing the first-party cookie on first call. Returns null on any error.
     */
    public static function id(): ?int
    {
        if (self::$resolved) {
            return self::$sessionId;
        }
        self::$resolved = true;

        try {
            $token = self::resolveToken();
            if ($token === null) {
                return null;
            }

            $userId = self::currentUserId();
            $customerId = self::currentCustomerId();
            $now = date('Y-m-d H:i:s');

            $row = Database::selectOne(
                'SELECT id, user_id, customer_id FROM tracking_sessions WHERE session_token = ?',
                [$token]
            );

            if ($row === null) {
                $uaHash = hash('sha256', self::userAgent() . '|' . self::salt());
                self::$sessionId = Database::insert(
                    'INSERT INTO tracking_sessions '
                    . '(session_token, user_id, customer_id, device_type, referral_source, user_agent_hash, is_bot, first_seen_at, last_seen_at, created_at) '
                    . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        $token,
                        $userId,
                        $customerId,
                        self::deviceType(),
                        self::referralSource(),
                        $uaHash,
                        self::isBot() ? 1 : 0,
                        $now,
                        $now,
                        $now,
                    ]
                );
                return self::$sessionId;
            }

            self::$sessionId = (int) $row['id'];

            // Touch last_seen and link identity if the visitor has since signed in.
            $needsLink = ($userId !== null && (int) ($row['user_id'] ?? 0) !== $userId)
                || ($customerId !== null && (int) ($row['customer_id'] ?? 0) !== $customerId);

            if ($needsLink) {
                Database::query(
                    'UPDATE tracking_sessions SET user_id = COALESCE(?, user_id), customer_id = COALESCE(?, customer_id), '
                    . 'linked_at = COALESCE(linked_at, ?), last_seen_at = ? WHERE id = ?',
                    [$userId, $customerId, $now, $now, self::$sessionId]
                );
            } else {
                Database::query('UPDATE tracking_sessions SET last_seen_at = ? WHERE id = ?', [$now, self::$sessionId]);
            }

            return self::$sessionId;
        } catch (Throwable) {
            return self::$sessionId;
        }
    }

    /** Public device-type accessor (mobile|tablet|desktop|bot|unknown). */
    public static function deviceType(): string
    {
        $ua = strtolower(self::userAgent());
        if ($ua === '') {
            return 'unknown';
        }
        if (self::isBot()) {
            return 'bot';
        }
        if (str_contains($ua, 'ipad') || (str_contains($ua, 'android') && !str_contains($ua, 'mobile')) || str_contains($ua, 'tablet')) {
            return 'tablet';
        }
        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'ipod') || str_contains($ua, 'android')) {
            return 'mobile';
        }
        return 'desktop';
    }

    public static function isBot(): bool
    {
        $ua = strtolower(self::userAgent());
        if ($ua === '') {
            return false;
        }
        foreach (['bot', 'crawl', 'spider', 'slurp', 'bingpreview', 'facebookexternalhit', 'headless', 'python-requests', 'curl/', 'wget'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }

    public static function referralSource(): ?string
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

    // ----- internals -----------------------------------------------------

    private static function resolveToken(): ?string
    {
        $existing = $_COOKIE[self::COOKIE] ?? null;
        if (is_string($existing) && preg_match('/^[a-f0-9]{40}$/', $existing) === 1) {
            return $existing;
        }

        // CLI / no-output contexts cannot set cookies; skip silently.
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return null;
        }

        $token = bin2hex(random_bytes(20)); // 40 hex chars
        $secure = (($_SERVER['HTTPS'] ?? '') !== '') && strtolower((string) ($_SERVER['HTTPS'] ?? '')) !== 'off';
        setcookie(self::COOKIE, $token, [
            'expires'  => time() + self::LIFETIME_DAYS * 86400,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
        return $token;
    }

    private static function currentUserId(): ?int
    {
        try {
            $auth = Auth::instance();
            return $auth->check() ? $auth->id() : null;
        } catch (Throwable) {
            return null;
        }
    }

    private static function currentCustomerId(): ?int
    {
        try {
            $userId = self::currentUserId();
            if ($userId === null) {
                return null;
            }
            $id = Database::scalar('SELECT id FROM customers WHERE user_id = ? LIMIT 1', [$userId]);
            return $id === false || $id === null ? null : (int) $id;
        } catch (Throwable) {
            return null;
        }
    }

    private static function userAgent(): string
    {
        return substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500);
    }

    private static function salt(): string
    {
        return (string) config('app.key', 'vanassist');
    }
}
