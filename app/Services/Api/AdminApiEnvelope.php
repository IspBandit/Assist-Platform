<?php

declare(strict_types=1);

namespace App\Services\Api;

use App\Core\Response;
use App\Platform\Support\RequestContext;

/**
 * Stable JSON envelopes for `/api/v1/admin` (docs/API.md, CORE-011).
 */
final class AdminApiEnvelope
{
    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $meta
     */
    public static function data(array $data, int $status = 200, array $meta = []): Response
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return self::respond($payload, $status);
    }

    /**
     * @param list<mixed> $items
     * @param array<string,mixed> $meta
     * @param array<string,mixed> $links
     */
    public static function collection(array $items, array $meta = [], array $links = [], int $status = 200): Response
    {
        $payload = [
            'data' => array_values($items),
            'meta' => $meta,
        ];
        if ($links !== []) {
            $payload['links'] = $links;
        }

        return self::respond($payload, $status);
    }

    /**
     * @param array<string,list<string>>|null $fields
     * @param array<string,mixed> $meta
     */
    public static function error(
        string $code,
        string $message,
        int $status,
        ?array $fields = null,
        array $meta = []
    ): Response {
        $error = [
            'code' => $code,
            'message' => $message,
            'request_id' => self::requestId(),
        ];
        if ($fields !== null && $fields !== []) {
            $error['fields'] = $fields;
        }

        $payload = ['error' => $error];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return self::respond($payload, $status);
    }

    /**
     * @param array<string,mixed> $payload
     */
    private static function respond(array $payload, int $status): Response
    {
        return Response::json($payload, $status)
            ->withHeader('Cache-Control', 'private, no-store, max-age=0')
            ->withHeader('Pragma', 'no-cache')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Request-ID', self::requestId());
    }

    private static function requestId(): string
    {
        if (RequestContext::hasRequestId()) {
            return RequestContext::requestId();
        }

        return 'uninitialized';
    }
}
