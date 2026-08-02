<?php

declare(strict_types=1);

namespace App\Core\Exceptions;

use Throwable;

/**
 * API error that must render as a JSON Admin API envelope, never an HTML page.
 */
final class AdminApiException extends HttpException
{
    /**
     * @param array<string,list<string>> $fields
     * @param array<string,mixed> $meta
     */
    public function __construct(
        int $statusCode,
        private readonly string $errorCode,
        string $message = '',
        private readonly array $fields = [],
        private readonly array $meta = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($statusCode, $message !== '' ? $message : $errorCode, $previous);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string,list<string>> */
    public function fields(): array
    {
        return $this->fields;
    }

    /** @return array<string,mixed> */
    public function meta(): array
    {
        return $this->meta;
    }
}
