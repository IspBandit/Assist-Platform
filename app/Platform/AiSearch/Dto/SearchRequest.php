<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Dto;

/**
 * Incoming natural-language search request (platform internal).
 */
final class SearchRequest
{
    public function __construct(
        public readonly string $rawQuery,
        public readonly string $brandKey,
        public readonly ?int $brandDatabaseId,
        public readonly ?float $latitude,
        public readonly ?float $longitude,
        public readonly ?int $radiusKm,
        public readonly string $requestId,
        public readonly string $channel = 'ask_vanassist',
        public readonly ?int $sessionId = null,
        public readonly int $resultLimit = 20,
    ) {
    }
}
