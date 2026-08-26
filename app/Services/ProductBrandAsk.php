<?php

declare(strict_types=1);

namespace App\Services;

/** Deterministic first-line intent routing for TowSmart and TrailerWise. */
final class ProductBrandAsk
{
    /** @var array<string,array<string,list<string>>> */
    private const CATEGORY_PATTERNS = [
        'towsmart' => [
            'public-weighing' => ['weighbridge', 'weigh bridge', 'mobile weighing', 'weigh my'],
            'towbars-hitches' => ['towbar', 'tow bar', 'hitch', 'weight distribution'],
            'brakes-controllers' => ['brake', 'brake controller', 'breakaway'],
            'suspension-payload' => ['suspension', 'airbag', 'load support', 'payload', 'spring'],
            'towing-training' => ['training', 'towing lesson', 'learn to tow', 'reversing'],
            'towing-inspections' => ['inspection', 'compliance check', 'safety check'],
            'tyres-wheels' => ['tyre', 'tire', 'wheel'],
        ],
        'trailerwise' => [
            'mobile-trailer-services' => ['mobile', 'on site', 'onsite', 'roadside'],
            'trailer-repairs' => ['repair', 'service', 'fault', 'broken', 'maintenance'],
            'roadworthy-inspections' => ['roadworthy', 'inspection', 'certificate', 'certifier', 'compliance'],
            'tyres-wheels-bearings' => ['tyre', 'tire', 'wheel', 'bearing', 'hub'],
            'brakes-axles-suspension' => ['brake', 'axle', 'suspension', 'spring'],
            'auto-electrical' => ['electrical', 'wiring', 'light', 'plug', 'battery'],
            'fabrication-engineering' => ['fabrication', 'welding', 'chassis', 'engineering', 'modification'],
            'parts-accessories' => ['part', 'parts', 'accessory', 'accessories', 'component', 'components', 'spare'],
            'manufacturers-dealers' => ['manufacturer', 'builder', 'dealer', 'new trailer'],
        ],
    ];

    /** @return array{kind:string,category:?string,location:?string,heading:string,explanation:string,url:string,source:string} */
    public function resolve(string $brandId, string $query): array
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));
        $location = $this->location($normalised);
        $intentText = (string) preg_replace('/\b(?:near|in|around|at)\s+.+$/u', '', $normalised);

        if ($brandId === 'towsmart'
            && preg_match('/\b(what is|what does|meaning of|define)\b/u', $intentText) === 1
            && preg_match('/\b(gvm|gcm|atm|gtm|towball|tow ball|payload|tare)\b/u', $intentText) === 1) {
            return [
                'kind' => 'guidance', 'category' => null, 'location' => $location,
                'heading' => 'Read the towing definitions and calculation guide',
                'explanation' => 'TowSmart matched this to reviewed explanatory content. Confirm ratings for the exact vehicle and trailer before relying on a calculation.',
                'url' => url('tow-guide'), 'source' => 'TowSmart deterministic education matrix',
            ];
        }

        if ($brandId === 'trailerwise'
            && preg_match('/\b(registration rules|maintenance schedule|ownership guide|pre-trip checklist|pre trip checklist)\b/u', $intentText) === 1) {
            return [
                'kind' => 'guidance', 'category' => null, 'location' => $location,
                'heading' => 'Open trailer ownership and compliance guidance',
                'explanation' => 'TrailerWise matched this to its current rules and ownership pathway. Check the linked authority for your jurisdiction and trailer before acting.',
                'url' => url('rules'), 'source' => 'TrailerWise deterministic ownership-content matrix',
            ];
        }

        if ($brandId === 'towsmart' && preg_match('/\b(gvm|gcm|atm|gtm|towball|tow ball|payload|weight|mass|overweight|can i tow|safe to tow|capacity)\b/u', $intentText) === 1) {
            return [
                'kind' => 'calculator', 'category' => null, 'location' => $location,
                'heading' => 'Check the exact towing combination',
                'explanation' => 'TowSmart matched this to its calculation and safety guidance. Enter plate and manufacturer figures for the exact vehicle and trailer; the result is guidance, not certification.',
                'url' => url('calculator'), 'source' => 'TowSmart deterministic calculation-and-safety matrix',
            ];
        }

        foreach (self::CATEGORY_PATTERNS[$brandId] ?? [] as $category => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->contains($intentText, $pattern)) {
                    $params = ['category' => $category];
                    if ($location !== null) {
                        $params['location'] = $location;
                    }
                    return [
                        'kind' => 'providers', 'category' => $category, 'location' => $location,
                        'heading' => 'Search the matching specialist category',
                        'explanation' => 'This request matched a curated ' . ($brandId === 'towsmart' ? 'towing' : 'trailer') . ' service category. Confirm capabilities and current details with the business.',
                        'url' => url('providers?' . http_build_query($params)),
                        'source' => ($brandId === 'towsmart' ? 'TowSmart' : 'TrailerWise') . ' deterministic provider-category matrix',
                    ];
                }
            }
        }

        $params = [];
        if ($location !== null) {
            $params['location'] = $location;
        }
        return [
            'kind' => 'clarify', 'category' => null, 'location' => $location,
            'heading' => 'Choose a service category',
            'explanation' => 'The request did not match a specific curated category, so no unrelated business has been substituted. Choose a category or refine the service you need.',
            'url' => url('providers' . ($params !== [] ? '?' . http_build_query($params) : '')),
            'source' => ($brandId === 'towsmart' ? 'TowSmart' : 'TrailerWise') . ' deterministic zero-result safeguard',
        ];
    }

    private function contains(string $text, string $pattern): bool
    {
        return preg_match('/(^|[^a-z0-9])' . preg_quote($pattern, '/') . '([^a-z0-9]|$)/u', $text) === 1;
    }

    private function location(string $query): ?string
    {
        $matches = [];
        if (preg_match('/\b(?:near|in|around|at)\s+(.+?)\s*\??$/u', $query, $matches) !== 1) {
            return null;
        }
        $location = trim((string) ($matches[1] ?? ''), " \t\n\r\0\x0B,.-");
        return $location === '' ? null : mb_convert_case($location, MB_CASE_TITLE, 'UTF-8');
    }
}
