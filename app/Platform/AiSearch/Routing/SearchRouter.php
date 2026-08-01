<?php

declare(strict_types=1);

namespace App\Platform\AiSearch\Routing;

use App\Platform\AiSearch\Dto\Intent;

/**
 * Selects executable adapters from a validated intent (AI-1).
 */
final class SearchRouter
{
    /**
     * @return list<string>
     */
    public function adaptersFor(Intent $intent): array
    {
        $adapters = [];
        foreach ($intent->adapterKeys as $key) {
            if ($key === 'providers' || $key === 'stays') {
                $adapters[] = $key;
            }
        }
        if ($adapters === [] && $intent->providerCategoryKeys !== []) {
            $adapters[] = 'providers';
        }
        if ($adapters === [] && $intent->stayTypeKeys !== []) {
            $adapters[] = 'stays';
        }
        return array_values(array_unique($adapters));
    }
}
