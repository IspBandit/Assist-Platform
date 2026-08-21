<?php

declare(strict_types=1);

namespace App\Platform\DataSources;

use InvalidArgumentException;
use RuntimeException;

/**
 * Shared GET client for government dataset connectors with basic SSRF guards.
 */
class SimpleHttpClient
{
    /**
     * @param array<string,string> $headers
     * @return array{status:int,body:string}
     */
    public function get(string $url, array $headers = [], int $timeoutSeconds = 25, int $maxRedirects = 3): array
    {
        $url = $this->assertSafeUrl($url);
        $remaining = max(0, min(5, $maxRedirects));
        while (true) {
            $lines = [];
            foreach ($headers as $name => $value) {
                $lines[] = $name . ': ' . $value;
            }
            $context = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => max(5, min(90, $timeoutSeconds)),
                    'ignore_errors' => true,
                    'header' => $lines === [] ? '' : implode("\r\n", $lines),
                    'follow_location' => 0,
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $body = @file_get_contents($url, false, $context);
            if ($body === false) {
                throw new RuntimeException('The dataset endpoint could not be reached.');
            }
            $status = 0;
            $location = null;
            foreach ($http_response_header as $line) {
                if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) {
                    $status = (int) $m[1];
                }
                if (preg_match('/^Location:\s*(.+)$/i', $line, $m)) {
                    $location = trim($m[1]);
                }
            }
            if ($status >= 300 && $status < 400 && $location !== null && $location !== '' && $remaining > 0) {
                $url = $this->resolveRedirectUrl($url, $location);
                $url = $this->assertSafeUrl($url);
                $remaining--;
                continue;
            }
            return ['status' => $status, 'body' => $body];
        }
    }

    public function assertSafeUrl(string $url): string
    {
        $url = trim($url);
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Dataset URL is invalid.');
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['https', 'http'], true)) {
            throw new InvalidArgumentException('Dataset URL scheme is not allowed.');
        }
        $host = strtolower((string) $parts['host']);
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
            throw new InvalidArgumentException('Dataset host is not allowed.');
        }
        $ip = filter_var($host, FILTER_VALIDATE_IP)
            ? $host
            : gethostbyname($host);
        if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($ip)) {
                throw new InvalidArgumentException('Dataset host resolves to a private network address.');
            }
        } elseif (filter_var($host, FILTER_VALIDATE_IP) && $this->isPrivateIp($host)) {
            throw new InvalidArgumentException('Dataset host is a private network address.');
        }
        return $url;
    }

    private function resolveRedirectUrl(string $current, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }
        $parts = parse_url($current);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new InvalidArgumentException('Redirect base URL is invalid.');
        }
        $origin = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if (str_starts_with($location, '/')) {
            return $origin . $location;
        }
        $path = (string) ($parts['path'] ?? '/');
        $dir = str_contains($path, '/') ? substr($path, 0, (int) strrpos($path, '/') + 1) : '/';
        return $origin . $dir . $location;
    }

    private function isPrivateIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) === false;
    }
}
