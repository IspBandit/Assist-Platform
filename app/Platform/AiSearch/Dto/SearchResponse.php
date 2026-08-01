<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Dto;

/**
 * Aggregated platform search response (not a raw AI payload).
 */
final class SearchResponse
{
    /**
     * @param list<array<string,mixed>> $providers
     * @param list<array<string,mixed>> $stays
     * @param list<string> $messages
     */
    public function __construct(
        public readonly Intent $intent,
        public readonly array $providers,
        public readonly array $stays,
        public readonly ?array $town,
        public readonly ?float $originLat,
        public readonly ?float $originLng,
        public readonly string $fallbackReason,
        public readonly array $messages,
        public readonly ?int $assistSearchId,
        public readonly bool $searched,
    ) {
    }

    public function localResultCount(): int
    {
        return count($this->providers) + count($this->stays);
    }
}
