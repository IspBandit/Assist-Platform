<?php

declare(strict_types=1);

/**
 * Probe Assist Platform Admin API health and optional service-account token.
 *
 * Usage:
 *   php scripts/admin-api-probe.php --base-url=https://staging.example/api/v1/admin
 *   php scripts/admin-api-probe.php --base-url=... --client-key=... --client-secret=...
 *
 * Credentials may also come from ADMIN_API_PROBE_CLIENT_KEY / ADMIN_API_PROBE_CLIENT_SECRET.
 * Never commit real secrets.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script must be run from the command line.\n");
    exit(1);
}

$options = getopt('', ['base-url:', 'client-key::', 'client-secret::', 'help']);
if (isset($options['help']) || !isset($options['base-url'])) {
    echo "Usage: php scripts/admin-api-probe.php --base-url=URL [--client-key=KEY --client-secret=SECRET]\n";
    exit(isset($options['help']) ? 0 : 1);
}

$baseUrl = rtrim((string) $options['base-url'], '/');
$clientKey = trim((string) ($options['client-key'] ?? getenv('ADMIN_API_PROBE_CLIENT_KEY') ?: ''));
$clientSecret = trim((string) ($options['client-secret'] ?? getenv('ADMIN_API_PROBE_CLIENT_SECRET') ?: ''));

/**
 * @return array{status:int,body:string}
 */
function admin_api_http(string $method, string $url, ?array $json = null, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('Unable to initialise cURL.');
    }

    $requestHeaders = array_merge(['Accept: application/json'], $headers);
    $payload = null;
    if ($json !== null) {
        $payload = json_encode($json, JSON_THROW_ON_ERROR);
        $requestHeaders[] = 'Content-Type: application/json';
    }

    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_POSTFIELDS => $payload,
    ]);

    $body = curl_exec($ch);
    if ($body === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new RuntimeException('HTTP request failed: ' . $error);
    }

    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['status' => $status, 'body' => (string) $body];
}

$failures = 0;

foreach (['/health', '/capabilities'] as $path) {
    try {
        $response = admin_api_http('GET', $baseUrl . $path);
    } catch (Throwable $e) {
        fwrite(STDERR, "FAIL {$path}: {$e->getMessage()}\n");
        ++$failures;
        continue;
    }

    $ok = $response['status'] >= 200 && $response['status'] < 300;
    echo ($ok ? 'OK  ' : 'FAIL') . " {$path} HTTP {$response['status']}\n";
    if (!$ok) {
        ++$failures;
        echo substr($response['body'], 0, 400) . "\n";
        continue;
    }

    $decoded = json_decode($response['body'], true);
    if (!is_array($decoded) || !array_key_exists('data', $decoded)) {
        echo "WARN {$path}: response is not a data envelope\n";
        ++$failures;
    } elseif ($path === '/capabilities') {
        $auth = $decoded['data']['authentication']['mfa_verify'] ?? null;
        echo '     mfa_verify=' . (is_string($auth) ? $auth : 'unknown') . "\n";
        echo '     enabled=' . json_encode($decoded['data']['enabled'] ?? null) . "\n";
    }
}

if ($clientKey !== '' && $clientSecret !== '') {
    try {
        $tokenResponse = admin_api_http('POST', $baseUrl . '/auth/token', [
            'client_key' => $clientKey,
            'client_secret' => $clientSecret,
        ]);
    } catch (Throwable $e) {
        fwrite(STDERR, 'FAIL /auth/token: ' . $e->getMessage() . "\n");
        exit(1);
    }

    $ok = $tokenResponse['status'] >= 200 && $tokenResponse['status'] < 300;
    echo ($ok ? 'OK  ' : 'FAIL') . " /auth/token HTTP {$tokenResponse['status']}\n";
    if (!$ok) {
        echo substr($tokenResponse['body'], 0, 400) . "\n";
        exit(1);
    }

    $decoded = json_decode($tokenResponse['body'], true);
    $access = is_array($decoded) ? ($decoded['data']['access_token'] ?? null) : null;
    if (!is_string($access) || $access === '') {
        fwrite(STDERR, "FAIL /auth/token: missing access_token in data envelope\n");
        exit(1);
    }

    $me = admin_api_http('GET', $baseUrl . '/auth/me', null, ['Authorization: Bearer ' . $access]);
    $meOk = $me['status'] >= 200 && $me['status'] < 300;
    echo ($meOk ? 'OK  ' : 'FAIL') . " /auth/me HTTP {$me['status']}\n";
    if (!$meOk) {
        ++$failures;
    }
} else {
    echo "SKIP /auth/token (no client credentials provided)\n";
}

exit($failures > 0 ? 1 : 0);
