<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Exceptions\AdminApiException;

/**
 * RFC 6238 TOTP helper for Admin API MFA (OPS-010).
 *
 * Pure PHP — no third-party OTP dependency. Secrets are base32-encoded.
 */
final class AdminApiTotp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes(max(10, $bytes)));
    }

    public static function provisioningUri(string $secret, string $accountName, string $issuer = 'Assist Platform Admin API'): string
    {
        $label = rawurlencode($issuer . ':' . $accountName);
        $query = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => 6,
            'period' => 30,
        ], '', '&', PHP_QUERY_RFC3986);

        return 'otpauth://totp/' . $label . '?' . $query;
    }

    public static function verify(string $code, string $secret, int $window = 1, ?int $timestamp = null): bool
    {
        $code = preg_replace('/\s+/', '', trim($code)) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        $timestamp ??= time();
        $counter = intdiv($timestamp, 30);
        for ($offset = -$window; $offset <= $window; ++$offset) {
            if (hash_equals(self::codeAt($secret, $counter + $offset), $code)) {
                return true;
            }
        }

        return false;
    }

    public static function codeAt(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            throw new AdminApiException(500, 'internal_error', 'MFA secret is malformed.');
        }

        $binaryCounter = pack('N*', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $value = (
            ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff)
        );

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    public static function base32Encode(string $data): string
    {
        $binary = '';
        foreach (str_split($data) as $char) {
            $binary .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }
        $binary = str_pad($binary, (int) (ceil(strlen($binary) / 5) * 5), '0');
        $encoded = '';
        foreach (str_split($binary, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                break;
            }
            $encoded .= self::ALPHABET[bindec($chunk)];
        }

        return $encoded;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(preg_replace('/[^A-Z2-7]/', '', $encoded) ?? '');
        if ($encoded === '') {
            return '';
        }

        $map = array_flip(str_split(self::ALPHABET));
        $binary = '';
        foreach (str_split($encoded) as $char) {
            if (!isset($map[$char])) {
                return '';
            }
            $binary .= str_pad(decbin($map[$char]), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($binary, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
