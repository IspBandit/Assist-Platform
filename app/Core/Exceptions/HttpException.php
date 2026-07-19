<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use RuntimeException;
use Throwable;

/**
 * Represents an HTTP error that should be rendered with a friendly page
 * (404, 403, 405, 500, etc.) rather than a stack trace.
 */
class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        string $message = '',
        ?Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
