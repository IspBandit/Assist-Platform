<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Provider;

final class AiCompletionResult
{
    /**
     * @param array<string,mixed>|null $parsed
     */
    public function __construct(
        public readonly bool $ok,
        public readonly ?array $parsed,
        public readonly string $provider,
        public readonly string $model,
        public readonly int $inputTokens,
        public readonly int $outputTokens,
        public readonly float $estimatedCostAud,
        public readonly ?float $actualCostAud,
        public readonly int $durationMs,
        public readonly ?string $providerRequestId,
        public readonly ?string $failureReason,
        public readonly bool $refused = false,
    ) {
    }

    public static function failure(
        string $provider,
        string $model,
        string $reason,
        int $durationMs = 0,
        bool $refused = false,
    ): self {
        return new self(
            ok: false,
            parsed: null,
            provider: $provider,
            model: $model,
            inputTokens: 0,
            outputTokens: 0,
            estimatedCostAud: 0.0,
            actualCostAud: null,
            durationMs: $durationMs,
            providerRequestId: null,
            failureReason: $reason,
            refused: $refused,
        );
    }
}
