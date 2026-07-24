<?php

declare(strict_types=1);

namespace App\Services;

final class GraphMailHealth
{
    /** @param array<string,mixed> $mail */
    public static function inspect(array $mail): array
    {
        $graph = is_array($mail['graph'] ?? null) ? $mail['graph'] : [];
        $certificate = self::resolvePath((string) ($graph['certificate_path'] ?? ''));
        $privateKey = self::resolvePath((string) ($graph['private_key_path'] ?? ''));
        $result = [
            'driver' => strtolower((string) ($mail['driver'] ?? '')),
            'mailbox' => (string) ($graph['mailbox'] ?? ''),
            'certificate_present' => is_file($certificate),
            'private_key_readable' => is_file($privateKey) && is_readable($privateKey),
            'expires_at' => null,
            'days_remaining' => null,
            'fingerprint' => null,
            'status' => 'missing',
        ];

        if (!$result['certificate_present'] || !$result['private_key_readable']) {
            return $result;
        }

        $pem = file_get_contents($certificate);
        $parsed = is_string($pem) ? openssl_x509_parse($pem) : false;
        if (!is_array($parsed) || !isset($parsed['validTo_time_t'])) {
            $result['status'] = 'invalid';
            return $result;
        }

        $expires = (int) $parsed['validTo_time_t'];
        $days = (int) floor(($expires - time()) / 86400);
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $pem), true);
        $result['expires_at'] = date(DATE_ATOM, $expires);
        $result['days_remaining'] = $days;
        $result['fingerprint'] = $der === false ? null : strtoupper(implode(':', str_split(hash('sha256', $der), 2)));
        $result['status'] = $days < 0 ? 'expired' : ($days <= 30 ? 'critical' : ($days <= 90 ? 'warning' : 'healthy'));

        return $result;
    }

    private static function resolvePath(string $path): string
    {
        if ($path === '') {
            return '';
        }

        return preg_match('/^(?:[A-Za-z]:[\\\/]|\/)/', $path) ? $path : base_path($path);
    }
}
