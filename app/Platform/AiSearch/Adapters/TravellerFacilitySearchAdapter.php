<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Platform\AiSearch\Dto\Intent;

/**
 * Stub until DATA-012 / AI-6 traveller_facilities entity ships (ADR 0016/0027).
 */
final class TravellerFacilitySearchAdapter
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
