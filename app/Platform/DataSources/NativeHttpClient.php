<?php
declare(strict_types=1);

namespace App\Platform\DataSources;

use RuntimeException;

final class NativeHttpClient implements HttpClientInterface
{
    public function postJson(string $url, array $headers, array $payload): array
    {
        $lines = ['Content-Type: application/json'];
        foreach ($headers as $name => $value) { $lines[] = $name . ': ' . $value; }
        $context = stream_context_create(['http' => [
            'method' => 'POST', 'timeout' => 20, 'ignore_errors' => true,
            'header' => implode("\r\n", $lines),
            'content' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) { throw new RuntimeException('The data source could not be reached.'); }
        $status = 0;
        foreach ($http_response_header as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $line, $m)) { $status = (int) $m[1]; break; }
        }
        return ['status' => $status, 'body' => $body];
    }
}
