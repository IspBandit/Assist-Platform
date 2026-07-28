<?php

declare(strict_types=1);

namespace App\Core;

/** Removes credentials and bearer tokens before persistence or display. */
final class SecretRedactor
{
    public static function redact(string $value): string
    {
        $patterns = [
            '/("(?:access_token|client_secret|client_assertion|refresh_token)"\s*:\s*")[^"]+("?)/i' => '$1[REDACTED]$2',
            '/\bBearer\s+[A-Za-z0-9._~+\/-]+=*/i' => 'Bearer [REDACTED]',
            '/\beyJ[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+\b/' => '[REDACTED JWT]',
        ];

        return (string) preg_replace(array_keys($patterns), array_values($patterns), $value);
    }

    public static function context(mixed $value): mixed
    {
        if (is_string($value)) {
            return self::redact($value);
        }
        if (!is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            if (is_string($key) && preg_match('/token|secret|password|assertion|private.?key/i', $key) === 1) {
                $value[$key] = '[REDACTED]';
                continue;
            }
            $value[$key] = self::context($item);
        }
        return $value;
    }
}
