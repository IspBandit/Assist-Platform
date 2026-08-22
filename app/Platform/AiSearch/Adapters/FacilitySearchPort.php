<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Adapters;

use App\Platform\AiSearch\Dto\Intent;

interface FacilitySearchPort
{
    /**
     * @param array<string,mixed>|null $town
     * @return list<array<string,mixed>>
     */
    public function search(Intent $intent, ?array $town = null, ?float $lat = null, ?float $lng = null): array;
}
