<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Session;
use App\Core\View;
use App\Helpers\Env;
use App\Auth\Auth;
use App\Platform\Brand\Brand;
use App\Platform\Brand\BrandContext;

if (!function_exists('e')) {
    /** HTML-escape a value for safe output. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('current_brand')) {
    /** Return the brand resolved for the current HTTP request or CLI command. */
    function current_brand(): Brand
    {
        return BrandContext::current();
    }
}

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        return BASE_PATH . ($path !== '' ? '/' . ltrim($path, '/') : '');
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        return base_path('storage' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('config_path')) {
    function config_path(string $path = ''): string
    {
        return base_path('config' . ($path !== '' ? '/' . ltrim($path, '/') : ''));
    }
}

if (!function_exists('app_base_url')) {
    /**
     * Resolve the base URL. Prefer a real configured APP_URL (trusted — avoids
     * host-header injection in queued emails). Fall back to the current request
     * host when APP_URL is unset/placeholder (e.g. during installation).
     */
    function app_base_url(): string
    {
        // Once the request/command has resolved a trusted brand, its configured
        // canonical URL is authoritative. This keeps links, assets, redirects,
        // sitemaps and queued brand email URLs on the correct domain while
        // avoiding direct trust in an arbitrary Host header.
        if (BrandContext::hasCurrent()) {
            return BrandContext::current()->url();
        }

        $configured = rtrim((string) config('app.url', ''), '/');
        if ($configured !== '' && $configured !== 'http://localhost') {
            return $configured;
        }

        if (PHP_SAPI !== 'cli' && !empty($_SERVER['HTTP_HOST'])) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', (string) $_SERVER['HTTP_HOST']);
            return ($https ? 'https' : 'http') . '://' . $host;
        }

        return $configured !== '' ? $configured : 'http://localhost';
    }
}

if (!function_exists('url')) {
    /** Build an absolute URL from an app-relative path. */
    function url(string $path = ''): string
    {
        return app_base_url() . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        $rel = ltrim($path, '/');
        $url = url('assets/' . $rel);

        // Cache-bust with the file's modification time so browsers (and phones)
        // pick up CSS/JS changes immediately after a deploy.
        $file = base_path('public/assets/' . $rel);
        $mtime = @filemtime($file);
        if ($mtime !== false) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'v=' . $mtime;
        }

        return $url;
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = []): string
    {
        return View::render($template, $data);
    }
}

if (!function_exists('redirect_location')) {
    /**
     * Resolve an application path or explicitly supported external contact URL.
     * Other schemes and control characters are rejected.
     */
    function redirect_location(string $target): string
    {
        if (str_contains($target, "\r") || str_contains($target, "\n") || str_contains($target, "\0")) {
            throw new InvalidArgumentException('Redirect target contains prohibited control characters.');
        }

        $scheme = strtolower((string) parse_url($target, PHP_URL_SCHEME));
        if ($scheme === '') {
            return url($target);
        }
        if (in_array($scheme, ['tel', 'mailto'], true)) {
            return $target;
        }
        if (in_array($scheme, ['http', 'https'], true) && is_string(parse_url($target, PHP_URL_HOST))) {
            return $target;
        }

        throw new InvalidArgumentException('Redirect target uses an unsupported scheme.');
    }
}

if (!function_exists('safe_back_url')) {
    /** Keep return navigation on the current configured origin. */
    function safe_back_url(?string $referer): string
    {
        $fallback = url('/');
        if ($referer === null || $referer === '') {
            return $fallback;
        }

        $expectedHost = strtolower((string) parse_url(app_base_url(), PHP_URL_HOST));
        $actualHost = strtolower((string) parse_url($referer, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($referer, PHP_URL_SCHEME));
        if ($expectedHost === '' || $actualHost !== $expectedHost || !in_array($scheme, ['http', 'https'], true)) {
            return $fallback;
        }

        return $referer;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): never
    {
        $location = redirect_location($path);
        header('Location: ' . $location, true, $status);
        exit;
    }
}

if (!function_exists('back')) {
    function back(): never
    {
        redirect(safe_back_url(isset($_SERVER['HTTP_REFERER']) ? (string) $_SERVER['HTTP_REFERER'] : null));
    }
}

if (!function_exists('session')) {
    function session(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return Session::all();
        }
        return Session::get($key, $default);
    }
}

if (!function_exists('old')) {
    /** Retrieve flashed old input for repopulating forms after validation errors. */
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_old_input', []);
        return $old[$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth')) {
    function auth(): Auth
    {
        return Auth::instance();
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return Auth::instance()->user();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return Auth::instance()->can($permission);
    }
}

if (!function_exists('is_role')) {
    function is_role(string ...$roles): bool
    {
        return Auth::instance()->hasAnyRole(...$roles);
    }
}

if (!function_exists('old_flash')) {
    function old_flash(): mixed
    {
        return Session::pull('_flash');
    }
}

if (!function_exists('str_slug')) {
    function str_slug(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
        return trim($value, '-');
    }
}

if (!function_exists('e_attr')) {
    /** Escape a value for use inside an HTML attribute. */
    function e_attr(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('provider_founding_promo_active')) {
    /** Whether the logged-in provider has a founding free-graphic promotion record. */
    function provider_founding_promo_active(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $user = current_user();
        if ($user === null) {
            $cached = false;

            return false;
        }
        $row = \App\Core\Database::selectOne(
            'SELECT id FROM providers WHERE user_id = ? AND deleted_at IS NULL',
            [(int) $user['id']]
        );
        if ($row === null) {
            $cached = false;

            return false;
        }
        $cached = \App\Services\FoundingGraphicService::forProvider((int) $row['id']) !== null;

        return $cached;
    }
}
