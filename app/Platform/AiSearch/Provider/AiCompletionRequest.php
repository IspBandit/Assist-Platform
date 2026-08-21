<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Provider;

/**
 * @phpstan-type MessageList list<array{role:string,content:string}>
 */
final class AiCompletionRequest
{
    /**
     * @param MessageList $messages
     * @param array<string,mixed> $jsonSchema
     */
    public function __construct(
        public readonly string $model,
        public readonly array $messages,
        public readonly array $jsonSchema,
        public readonly string $schemaName,
        public readonly int $maxOutputTokens,
        public readonly int $timeoutSeconds,
        public readonly string $correlationId,
        public readonly ?string $cacheKeyHint = null,
    ) {
    }
}
