<?php

declare(strict_types=1);

namespace App\Platform\Support;

use App\Core\Config;
use App\Platform\Brand\BrandRegistry;
use RuntimeException;
use Throwable;

final class EnvironmentValidator
{
    public static function validateInstalledApplication(): void
    {
        $errors = [];
        $environment = (string) Config::get('app.env', '');
        if (!in_array($environment, ['local', 'test', 'staging', 'production'], true)) {
            $errors[] = 'APP_ENV must be local, test, staging, or production';
        }

        $url = (string) Config::get('app.url', '');
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'APP_URL must be an absolute HTTP(S) URL';
        }

        foreach (['database.name' => 'DB_NAME', 'database.user' => 'DB_USER'] as $key => $label) {
            if (trim((string) Config::get($key, '')) === '') {
                $errors[] = "{$label} is required for an installed application";
            }
        }

        if ($environment === 'production') {
            if ((bool) Config::get('app.debug', false)) {
                $errors[] = 'APP_DEBUG must be false in production';
            }
            if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
                $errors[] = 'APP_URL must use HTTPS in production';
            }
            if (strlen((string) Config::get('app.key', '')) < 32) {
                $errors[] = 'APP_KEY must contain at least 32 characters in production';
            }
            if (!(bool) Config::get('security.session.secure', false)) {
                $errors[] = 'SESSION_SECURE must be true in production';
            }
            if ((bool) Config::get('brands.allow_development_fallback', false)) {
                $errors[] = 'ASSIST_ALLOW_BRAND_QUERY must be false in production';
            }
            if (!(bool) Config::get('brands.strict_hosts', false)) {
                $errors[] = 'ASSIST_STRICT_BRAND_HOSTS must be true in production';
            }
        }

        if ((bool) Config::get('billing.enabled', false)) {
            $errors[] = 'Production billing cannot be enabled until gateway lifecycle operations are implemented and approved';
        }

        foreach ((array) Config::get('security.trusted_proxies', []) as $proxy) {
            if (!is_string($proxy) || filter_var($proxy, FILTER_VALIDATE_IP) === false) {
                $errors[] = 'TRUSTED_PROXIES must contain exact valid IP addresses';
                break;
            }
        }

        try {
            $registry = Config::get('brands.registry', []);
            if (!is_array($registry)) {
                throw new RuntimeException('brand registry is not an array');
            }
            BrandRegistry::fromArray($registry);
        } catch (Throwable $e) {
            $errors[] = 'Brand registry is invalid: ' . $e->getMessage();
        }

        if ($errors !== []) {
            throw new RuntimeException(
                "Invalid Assist Platform environment:\n- " . implode("\n- ", $errors)
            );
        }
    }
}
