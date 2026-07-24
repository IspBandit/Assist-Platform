<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/** App-only Microsoft Graph mail transport using a certificate credential. */
final class MicrosoftGraphMailClient
{
    private static ?string $token = null;
    private static int $tokenExpiresAt = 0;

    /** @param array<string,mixed> $cfg */
    public static function send(array $cfg, string $to, string $recipientName, string $subject, string $html, string $text): void
    {
        $from = trim((string) ($cfg['from_address'] ?? ''));
        if ($from === '') { throw new RuntimeException('Microsoft Graph brand sender mailbox is not configured.'); }
        $payload = [
            'message' => [
                'subject' => $subject,
                'body' => ['contentType' => 'HTML', 'content' => $html !== '' ? $html : nl2br(htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'))],
                'from' => ['emailAddress' => ['name' => (string) $cfg['from_name'], 'address' => $from]],
                'replyTo' => [['emailAddress' => ['name' => (string) $cfg['from_name'], 'address' => $from]]],
                'toRecipients' => [['emailAddress' => ['name' => $recipientName, 'address' => $to]]],
            ],
            'saveToSentItems' => false,
        ];
        self::request(
            self::sendingEndpoint($from),
            json_encode($payload, JSON_THROW_ON_ERROR),
            ['Authorization: Bearer ' . self::token($cfg), 'Content-Type: application/json'],
            [202]
        );
    }

    private static function sendingEndpoint(string $from): string
    {
        return 'https://graph.microsoft.com/v1.0/users/' . rawurlencode($from) . '/sendMail';
    }

    /** @param array<string,mixed> $cfg */
    private static function token(array $cfg): string
    {
        if (self::$token !== null && self::$tokenExpiresAt > time() + 120) { return self::$token; }
        $tenant = trim((string) ($cfg['graph_tenant_id'] ?? ''));
        $client = trim((string) ($cfg['graph_client_id'] ?? ''));
        $certPath = self::resolvePath((string) ($cfg['graph_certificate_path'] ?? ''));
        $keyPath = self::resolvePath((string) ($cfg['graph_private_key_path'] ?? ''));
        if ($tenant === '' || $client === '' || !is_file($certPath) || !is_file($keyPath)) {
            throw new RuntimeException('Microsoft Graph certificate configuration is incomplete.');
        }
        $certPem = (string) file_get_contents($certPath);
        $der = base64_decode((string) preg_replace('/-----[^-]+-----|\s+/', '', $certPem), true);
        if ($der === false) { throw new RuntimeException('Microsoft Graph public certificate is invalid.'); }
        $b64 = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $b64(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'x5t' => $b64(sha1($der, true))], JSON_THROW_ON_ERROR));
        $now = time();
        $claims = $b64(json_encode(['aud' => "https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", 'iss' => $client, 'sub' => $client, 'jti' => bin2hex(random_bytes(16)), 'nbf' => $now - 30, 'exp' => $now + 600], JSON_THROW_ON_ERROR));
        $unsigned = $header . '.' . $claims;
        $privateKey = openssl_pkey_get_private((string) file_get_contents($keyPath), (string) ($cfg['graph_private_key_password'] ?? ''));
        if ($privateKey === false || !openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the Microsoft Graph client assertion.');
        }
        $assertion = $unsigned . '.' . $b64($signature);
        $body = http_build_query(['client_id' => $client, 'scope' => 'https://graph.microsoft.com/.default', 'grant_type' => 'client_credentials', 'client_assertion_type' => 'urn:ietf:params:oauth:client-assertion-type:jwt-bearer', 'client_assertion' => $assertion]);
        $response = self::request("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", $body, ['Content-Type: application/x-www-form-urlencoded'], [200]);
        $decoded = json_decode($response, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || empty($decoded['access_token'])) { throw new RuntimeException('Microsoft Graph did not return an access token.'); }
        self::$token = (string) $decoded['access_token'];
        self::$tokenExpiresAt = $now + (int) ($decoded['expires_in'] ?? 3600);
        return self::$token;
    }

    /** @param list<string> $headers @param list<int> $expected */
    private static function request(string $url, string $body, array $headers, array $expected): string
    {
        $context = stream_context_create(['http' => ['method' => 'POST', 'header' => implode("\r\n", $headers), 'content' => $body, 'ignore_errors' => true, 'timeout' => 30]]);
        $response = @file_get_contents($url, false, $context);
        $statusLine = $http_response_header[0] ?? '';
        preg_match('/\s(\d{3})\s/', $statusLine, $matches);
        $status = (int) ($matches[1] ?? 0);
        if (!in_array($status, $expected, true)) {
            $safe = is_string($response) ? mb_substr(strip_tags($response), 0, 500) : 'No response body';
            throw new RuntimeException("Microsoft Graph request failed with HTTP {$status}: {$safe}");
        }
        return is_string($response) ? $response : '';
    }

    private static function resolvePath(string $path): string
    {
        if ($path === '') { return ''; }
        return preg_match('/^(?:[A-Za-z]:[\\\\\/]|\/)/', $path) ? $path : base_path($path);
    }
}
