<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Provider;

/**
 * Vendor-neutral structured AI completion port (ADR 0020).
 */
interface AiProviderInterface
{
    public function name(): string;

    public function completeStructured(AiCompletionRequest $request): AiCompletionResult;
}
