<?php

declare(strict_types=1);

namespace App\Core;

use App\Auth\Auth;
use App\Core\Exceptions\HttpException;
use App\Helpers\Env;
use App\Middleware\SecurityHeaders;
use App\Platform\Brand\BrandContext;
use App\Platform\Brand\BrandRegistry;
use App\Platform\Brand\BrandResolver;
use App\Platform\Brand\BrandRoutePolicy;
use App\Platform\Support\EnvironmentValidator;
use App\Platform\Support\RequestContext;
use App\Services\Settings;
use Throwable;

/**
 * Application kernel: boots configuration, services and routing, then
 * handles the request lifecycle including installation and maintenance gating.
 */
final class Kernel
{
    private Router $router;
    private BrandResolver $brandResolver;
    private bool $booted = false;

    public function boot(): void
    {
        if ($this->booted) {
            return;
        }

        Env::load(BASE_PATH . '/.env');
        Config::load(BASE_PATH . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'Australia/Brisbane'));
        ErrorHandler::register();

        $brandConfig = Config::get('brands.registry', []);
        if (!is_array($brandConfig)) {
            throw new \RuntimeException('Brand registry configuration must be an array');
        }
        $registry = BrandRegistry::fromArray($brandConfig);
        $this->brandResolver = new BrandResolver(
            $registry,
            (string) Config::get('brands.default', 'vanassist'),
            is_string(Config::get('brands.explicit')) ? Config::get('brands.explicit') : null,
            (string) Config::get('app.env', 'production'),
            (bool) Config::get('brands.allow_development_fallback', false),
            (bool) Config::get('brands.strict_hosts', false),
        );
        if (self::isInstalled()) {
            EnvironmentValidator::validateInstalledApplication();
        }

        Session::start();

        $this->router = new Router();
        $this->registerMiddlewareAliases();
        $this->loadRoutes();

        $this->booted = true;
    }

    public function run(): void
    {
        $this->boot();
        $request = Request::capture();
        RequestContext::begin($request);
        $response = (new SecurityHeaders())->handle(
            $request,
            fn (Request $request): Response => $this->handle($request)
        );
        if (!$response instanceof Response) {
            throw new \RuntimeException('Kernel middleware did not return a Response');
        }
        $response->send();

        // First-party page-view recording (no-op unless analytics is enabled).
        if (self::isInstalled()) {
            \App\Services\Analytics::record($request, $response);
        }
    }

    public function handle(Request $request): Response
    {
        $path = $request->path();
        if ($path === '/healthz') {
            return Response::json(['status' => 'ok'])
                ->withHeader('Cache-Control', 'no-store');
        }
        if ($path === '/readyz') {
            return $this->readinessResponse();
        }

        $brand = $this->brandResolver->resolve($request);
        BrandContext::set($brand);

        // Disabled/coming-soon brands must never fall through to VanAssist
        // routes. Their real configuration is deployable, but product modules
        // remain explicitly unavailable until implemented and tested.
        if (!$brand->moduleEnabled('public_application')) {
            return Response::html(View::render('brands.coming-soon', [
                'brand' => $brand,
            ]), 503)
                ->withHeader('Cache-Control', 'no-store')
                ->withHeader('Retry-After', '86400')
                ->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        // Alternate brand hosts are fail-closed: an enabled brand may only
        // reach routes explicitly belonging to its implemented modules.
        if (!(new BrandRoutePolicy())->allows($brand, $path)) {
            throw new HttpException(404, 'Page not found');
        }

        // 1. Installation gating. Until the lock file exists, force the installer.
        if (!self::isInstalled()) {
            if (!str_starts_with($path, '/install')) {
                return (new Response('', 302))->withHeader('Location', url('install'));
            }
        } elseif (str_starts_with($path, '/install')) {
            // Installer is locked once installed.
            return (new Response('', 302))->withHeader('Location', url('admin'));
        }

        // 2. Maintenance mode (admins bypass). Only relevant once installed.
        // Authentication routes are always reachable so staff/admins can sign in
        // even while the public site shows the holding page.
        if (self::isInstalled() && !str_starts_with($path, '/install') && !self::isAuthPath($path)) {
            try {
                $isAdmin     = Auth::instance()->hasAnyRole('administrator', Auth::SUPER_ADMIN);
                $maintenance = Settings::isMaintenanceMode();
                // "Private" launch mode hides the public site exactly like
                // maintenance, but with a "coming soon" framing.
                $private     = Settings::launchMode() === 'private';

                if (($maintenance || $private) && !$isAdmin) {
                    [$heading, $message] = $maintenance
                        ? ['We\'ll be back soon', Settings::get('maintenance_message', $brand->name() . ' is briefly offline for maintenance.')]
                        : ['Coming soon', $brand->name() . ' is not open to the public just yet. Please check back soon.'];

                    return Response::html(View::render('errors.maintenance', [
                        'status'  => 503,
                        'heading' => $heading,
                        'message' => $message,
                    ]), 503)
                        ->withHeader('Retry-After', '3600')
                        ->withHeader('Cache-Control', 'no-store')
                        ->withHeader('X-Robots-Tag', 'noindex, nofollow');
                }
            } catch (Throwable) {
                if ((string) Config::get('app.env', 'production') === 'production') {
                    return Response::html(View::render('errors.maintenance', [
                        'status' => 503,
                        'heading' => 'Service temporarily unavailable',
                        'message' => 'Please try again shortly.',
                    ]), 503)
                        ->withHeader('Retry-After', '60')
                        ->withHeader('Cache-Control', 'no-store')
                        ->withHeader('X-Robots-Tag', 'noindex, nofollow');
                }
            }
        }

        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            throw $e; // handled by ErrorHandler for friendly pages
        }
    }

    public function router(): Router
    {
        return $this->router;
    }

    private function readinessResponse(): Response
    {
        $ready = self::isInstalled();
        foreach (['storage', 'storage/cache', 'storage/logs', 'storage/sessions'] as $directory) {
            $path = BASE_PATH . '/' . $directory;
            $ready = $ready && is_dir($path) && is_writable($path);
        }
        if ($ready) {
            try {
                $ready = (int) Database::scalar('SELECT 1') === 1;
                $dirtyMigrations = (int) Database::scalar(
                    "SELECT COUNT(*) FROM migrations WHERE status <> 'succeeded'"
                );
                $ready = $ready && $dirtyMigrations === 0;
            } catch (Throwable) {
                $ready = false;
            }
        }

        $payload = ['status' => $ready ? 'ready' : 'unavailable'];
        $release = trim((string) Config::get('app.release', ''));
        if ($release !== '') {
            $payload['release'] = $release;
        }

        return Response::json($payload, $ready ? 200 : 503)
            ->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Whether the path is an authentication route that must stay reachable even
     * during maintenance/launch mode (otherwise admins could never sign in).
     */
    private static function isAuthPath(string $path): bool
    {
        foreach (['/login', '/logout', '/forgot-password', '/reset-password', '/verify-email'] as $prefix) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/') || str_starts_with($path, $prefix . '?')) {
                return true;
            }
        }
        return false;
    }

    public static function isInstalled(): bool
    {
        return is_file(BASE_PATH . '/storage/installed.lock');
    }

    private function registerMiddlewareAliases(): void
    {
        $this->router->aliasMiddleware('headers', \App\Middleware\SecurityHeaders::class);
        $this->router->aliasMiddleware('csrf', \App\Middleware\VerifyCsrf::class);
        $this->router->aliasMiddleware('auth', \App\Middleware\Authenticate::class);
        $this->router->aliasMiddleware('guest', \App\Middleware\GuestOnly::class);
        $this->router->aliasMiddleware('role', \App\Middleware\RequireRole::class);
        $this->router->aliasMiddleware('permission', \App\Middleware\RequirePermission::class);
        $this->router->aliasMiddleware('rate', \App\Middleware\RateLimit::class);
    }

    private function loadRoutes(): void
    {
        $router = $this->router;
        foreach (['web', 'auth', 'install', 'admin', 'account', 'provider', 'park'] as $file) {
            $routeFile = BASE_PATH . '/routes/' . $file . '.php';
            if (!is_file($routeFile)) {
                continue;
            }

            // Route files return a registrar closure, or (legacy) register inline
            // using $router from this scope and return 1.
            $register = require $routeFile;
            if (is_callable($register)) {
                $register($router);
                continue;
            }
            if ($register === 1 || $register === true) {
                continue;
            }

            throw new \RuntimeException(
                'Route file routes/' . $file . '.php must return a callable registrar '
                . 'or register routes inline; got ' . gettype($register) . '. '
                . 'Re-upload routes/' . $file . '.php from the repository (deploy with -Full if needed).'
            );
        }
    }
}
