<?php

declare(strict_types=1);

namespace App\Services\Polaris;

use App\Models\Provider;
use App\Platform\Brand\BrandRegistry;
use Throwable;

/**
 * Surfaces relevant VanAssist providers on Polaris pages without duplicating records.
 */
final class VanAssistSurfacingService
{
    /**
     * @return array{brand:string,providers:array<int,array<string,mixed>>,disclaimer:string}
     */
    public function relatedServices(?string $stateAbbr = null, int $limit = 6): array
    {
        $disclaimer = 'Service providers are listed on VanAssist. Polaris does not duplicate provider records.';
        try {
            $registry = BrandRegistry::fromArray((array) config('brands.registry', []));
            $vanassist = $registry->get('vanassist');
            $search = 'caravan repair';
            $result = Provider::brandDirectory($vanassist->databaseId(), null, null, $search, $limit, 0);
            $providers = $result['rows'];
            if ($stateAbbr !== null && $stateAbbr !== '') {
                $providers = array_values(array_filter(
                    $providers,
                    static fn (array $p): bool => strcasecmp((string) ($p['state_abbr'] ?? ''), $stateAbbr) === 0
                ));
            }
            foreach ($providers as &$provider) {
                $provider['vanassist_url'] = rtrim($vanassist->url(), '/') . '/providers/' . rawurlencode((string) $provider['slug']);
            }
            unset($provider);

            return [
                'brand' => 'vanassist',
                'providers' => array_slice($providers, 0, $limit),
                'disclaimer' => $disclaimer,
            ];
        } catch (Throwable) {
            return ['brand' => 'vanassist', 'providers' => [], 'disclaimer' => $disclaimer];
        }
    }
}
