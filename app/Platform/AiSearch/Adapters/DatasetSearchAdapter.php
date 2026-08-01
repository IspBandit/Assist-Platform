<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Platform\AiSearch\Dto\Intent;

/**
 * Stub until AI-5 dataset routing. Disabled in AI-1.
 */
final class DatasetSearchAdapter
{
    /**
     * @return list<array<string,mixed>>
     */
    public function search(Intent $intent): array
    {
        unset($intent);
        return [];
    }
}
