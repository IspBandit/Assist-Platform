<?php

declare(strict_types=1);

namespace App\Services;

final class ProviderServiceClassificationPolicy
{
    /** @var array<string, string> */
    private const NAME_SERVICE_RULES = [
        'windscreen|auto glass|automotive glass' => 'windscreen-and-auto-glass',
        'supercheap|autopro|auto parts|parts store|parts centre' => 'vehicle-parts-and-accessories',
        'tyre|tire|tyrepower|bob jane|bridgestone|goodyear' => 'tyres-and-wheels',
        'petroleum|service station|fuel stop|ampol|caltex|7-eleven' => 'fuel-and-travel-stops',
        'elgas|lpg refill|gas bottle|bottle exchange' => 'lpg-refills-and-bottle-exchange',
    ];

    public static function matchesSpecialistName(string $businessName): bool
    {
        foreach (array_keys(self::NAME_SERVICE_RULES) as $pattern) {
            if (preg_match('/' . $pattern . '/i', $businessName) === 1) {
                return true;
            }
        }

        return false;
    }

    public static function isUnsupportedSpecialistService(string $businessName, string $serviceSlug): bool
    {
        $matchedNameRule = false;
        foreach (self::NAME_SERVICE_RULES as $pattern => $allowedSlug) {
            if (preg_match('/' . $pattern . '/i', $businessName) !== 1) {
                continue;
            }
            $matchedNameRule = true;
            if ($serviceSlug === $allowedSlug) {
                return false;
            }
        }

        return $matchedNameRule;
    }
}
