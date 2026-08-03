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
     * @param list<array<string,mixed>> $externals
     * @param list<array<string,mixed>> $facilities
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
        public readonly array $externals = [],
        public readonly array $facilities = [],
        public readonly ?int $knowledgeGapId = null,
    ) {
    }

    public function localResultCount(): int
    {
        return count($this->providers) + count($this->stays) + count($this->facilities);
    }

    public function externalResultCount(): int
    {
        return count($this->externals);
    }
}
