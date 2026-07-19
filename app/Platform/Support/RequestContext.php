<?php

declare(strict_types=1);

namespace App\Platform\Support;

use App\Core\Request;
use RuntimeException;

final class RequestContext
{
    private static ?string $requestId = null;

    public static function begin(Request $request): string
    {
        $provided = trim((string) $request->header('X-Request-ID', ''));
        self::$requestId = preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]{7,127}$/', $provided)
            ? $provided
            : bin2hex(random_bytes(16));

        return self::$requestId;
    }

    public static function hasRequestId(): bool
    {
        return self::$requestId !== null;
    }

    public static function requestId(): string
    {
        if (self::$requestId === null) {
            throw new RuntimeException('Request context has not been initialized');
        }
        return self::$requestId;
    }

    public static function clear(): void
    {
        self::$requestId = null;
    }
}
