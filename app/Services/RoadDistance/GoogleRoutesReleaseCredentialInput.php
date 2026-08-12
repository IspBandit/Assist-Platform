<?php

declare(strict_types=1);

namespace App\Services\RoadDistance;

use InvalidArgumentException;

final class GoogleRoutesReleaseCredentialInput
{
    private const PREFIX = 'ASSIST_GOOGLE_ROUTES_CREDENTIAL_V1:';

    /**
     * Read an optional credential payload without blocking interactive migration runs.
     *
     * @param resource $stream
     */
    public static function read($stream): ?string
    {
        $read = [$stream];
        $write = null;
        $except = null;
        $available = @stream_select($read, $write, $except, 0, 0);
        if ($available !== 1) {
            return null;
        }

        $payload = stream_get_contents($stream, 512);
        if (!is_string($payload)) {
            throw new InvalidArgumentException('Unable to read protected release input.');
        }

        return self::parse($payload);
    }

    public static function parse(string $payload): ?string
    {
        $payload = trim($payload);
        if ($payload === '') {
            return null;
        }
        if (!str_starts_with($payload, self::PREFIX)) {
            throw new InvalidArgumentException('Protected release input has an invalid envelope.');
        }

        $credential = substr($payload, strlen(self::PREFIX));
        if ($credential === '' || str_contains($credential, "\n") || str_contains($credential, "\r")) {
            throw new InvalidArgumentException('Protected release input has an invalid credential payload.');
        }

        return $credential;
    }
}
