<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Wrapper around PHP native sessions with secure cookie settings,
 * CSRF token management and flash messages. Sessions are stored on disk
 * inside /storage/sessions (outside the web root).
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $cfg = Config::get('security.session');
        $sessionPath = base_path('storage/sessions');
        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);
        }

        session_name((string) ($cfg['name'] ?? 'vanassist_session'));

        session_set_cookie_params([
            'lifetime' => ((int) ($cfg['lifetime_minutes'] ?? 120)) * 60,
            'path'     => '/',
            'domain'   => '',
            'secure'   => (bool) ($cfg['secure'] ?? true),
            'httponly' => (bool) ($cfg['http_only'] ?? true),
            'samesite' => (string) ($cfg['same_site'] ?? 'Lax'),
        ]);

        session_start();
        self::$started = true;

        // Rotate flash: move "next" flash into the readable "_flash" bucket.
        self::ageFlash();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function has(string $key): bool
    {
        return isset($_SESSION[$key]);
    }

    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function all(): array
    {
        return $_SESSION ?? [];
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
        self::$started = false;
    }

    // ----- Flash messages -------------------------------------------------

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash_next'][$type] = $message;
    }

    public static function flashInput(array $input): void
    {
        $_SESSION['_old_input_next'] = $input;
    }

    public static function flashErrors(array $errors): void
    {
        $_SESSION['_errors_next'] = $errors;
    }

    public static function errors(): array
    {
        return $_SESSION['_errors'] ?? [];
    }

    public static function flashMessages(): array
    {
        return $_SESSION['_flash'] ?? [];
    }

    private static function ageFlash(): void
    {
        $_SESSION['_flash']     = $_SESSION['_flash_next'] ?? [];
        $_SESSION['_old_input'] = $_SESSION['_old_input_next'] ?? [];
        $_SESSION['_errors']    = $_SESSION['_errors_next'] ?? [];
        unset($_SESSION['_flash_next'], $_SESSION['_old_input_next'], $_SESSION['_errors_next']);
    }

    // ----- CSRF -----------------------------------------------------------

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        $stored = $_SESSION['_csrf_token'] ?? '';
        return $stored !== '' && is_string($token) && hash_equals($stored, $token);
    }
}
