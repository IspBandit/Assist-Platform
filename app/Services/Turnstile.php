<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;

/** Server-side Cloudflare Turnstile verification for public write forms. */
final class Turnstile
{
    public static function enabled(): bool
    {
        return (bool) Config::get('security.turnstile.enabled', false);
    }

    public static function siteKey(): string
    {
        return trim((string) Config::get('security.turnstile.site_key', ''));
    }

    public static function verify(Request $request): bool
    {
        if (!self::enabled()) {
            return true;
        }
        $secret = trim((string) Config::get('security.turnstile.secret_key', ''));
        $token = trim((string) $request->input('cf-turnstile-response', ''));
        if ($secret === '' || $token === '') {
            return false;
        }

        $payload = http_build_query([
            'secret' => $secret,
            'response' => $token,
            'remoteip' => $request->ip(),
        ]);
        $context = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
            'content' => $payload,
            'timeout' => 6,
            'ignore_errors' => true,
        ]]);
        $raw = @file_get_contents('https://challenges.cloudflare.com/turnstile/v0/siteverify', false, $context);
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $result = json_decode($raw, true);
        return is_array($result) && ($result['success'] ?? false) === true;
    }
}
