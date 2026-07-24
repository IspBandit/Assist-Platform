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

        if (!in_array((string) Config::get('app.launch_mode', ''), ['private', 'provider-onboarding', 'local-pilot', 'public'], true)) {
            $errors[] = 'LAUNCH_MODE must be private, provider-onboarding, local-pilot, or public';
        }

        $databasePort = (int) Config::get('database.port', 0);
        if ($databasePort < 1 || $databasePort > 65535) {
            $errors[] = 'DB_PORT must be between 1 and 65535';
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
            if (trim((string) Config::get('app.release', '')) === '') {
                $errors[] = 'APP_RELEASE is required in production';
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

        $mailDriver = strtolower(trim((string) Config::get('mail.driver', '')));
        if (!in_array($mailDriver, ['log', 'smtp', 'graph'], true)) {
            $errors[] = 'MAIL_DRIVER must be log, smtp, or graph';
        } elseif ($environment === 'production' && $mailDriver === 'log') {
            $errors[] = 'MAIL_DRIVER=log is not permitted in production';
        } elseif ($mailDriver === 'smtp') {
            if (trim((string) Config::get('mail.host', '')) === '') {
                $errors[] = 'MAIL_HOST is required when MAIL_DRIVER=smtp';
            }
            if (!in_array((string) Config::get('mail.encryption', ''), ['tls', 'ssl'], true)) {
                $errors[] = 'MAIL_ENCRYPTION must be tls or ssl when MAIL_DRIVER=smtp';
            }
            if (filter_var((string) Config::get('mail.from_address', ''), FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'MAIL_FROM_ADDRESS must be valid when MAIL_DRIVER=smtp';
            }
        } elseif ($mailDriver === 'graph') {
            $graphLabels = [
                'tenant_id' => 'MICROSOFT_GRAPH_TENANT_ID',
                'client_id' => 'MICROSOFT_GRAPH_CLIENT_ID',
                'certificate_path' => 'MICROSOFT_GRAPH_CERTIFICATE_PATH',
                'private_key_path' => 'MICROSOFT_GRAPH_PRIVATE_KEY_PATH',
                'mailbox' => 'MICROSOFT_GRAPH_SENDING_MAILBOX',
            ];
            foreach ($graphLabels as $key => $label) {
                if (trim((string) Config::get('mail.graph.' . $key, '')) === '') {
                    $errors[] = $label . ' is required when MAIL_DRIVER=graph';
                }
            }
            if (filter_var((string) Config::get('mail.graph.mailbox', ''), FILTER_VALIDATE_EMAIL) === false) {
                $errors[] = 'MICROSOFT_GRAPH_SENDING_MAILBOX must be a valid email address';
            }
        }

        if ((bool) Config::get('security.turnstile.enabled', false)) {
            if (trim((string) Config::get('security.turnstile.site_key', '')) === ''
                || trim((string) Config::get('security.turnstile.secret_key', '')) === '') {
                $errors[] = 'TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY are required when Turnstile is enabled';
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
