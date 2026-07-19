<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use RuntimeException;

final class SecretCipher
{
    private const PREFIX = 'enc:v1:';
    private const CIPHER = 'aes-256-gcm';
    private const AAD = 'assist-platform:settings:v1';

    public static function encrypt(string $plaintext): string
    {
        if ($plaintext === '') {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            self::AAD,
            16
        );
        if ($ciphertext === false || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt application secret');
        }

        return self::PREFIX . base64_encode($iv . $tag . $ciphertext);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '' || !str_starts_with($stored, self::PREFIX)) {
            // Legacy plaintext remains readable only to support an explicit,
            // one-time migration via scripts/encrypt-secrets.php.
            return $stored;
        }

        $payload = base64_decode(substr($stored, strlen(self::PREFIX)), true);
        if ($payload === false || strlen($payload) < 29) {
            throw new RuntimeException('Encrypted application secret is malformed');
        }

        $plaintext = openssl_decrypt(
            substr($payload, 28),
            self::CIPHER,
            self::key(),
            OPENSSL_RAW_DATA,
            substr($payload, 0, 12),
            substr($payload, 12, 16),
            self::AAD
        );
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt application secret; verify APP_KEY');
        }

        return $plaintext;
    }

    public static function encrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    private static function key(): string
    {
        $appKey = (string) Config::get('app.key', '');
        if (strlen($appKey) < 16) {
            throw new RuntimeException('APP_KEY must contain at least 16 characters to encrypt secrets');
        }

        return hash('sha256', $appKey, true);
    }
}
