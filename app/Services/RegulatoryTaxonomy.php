<?php

declare(strict_types=1);

namespace App\Services;

/** Keeps ordinary vehicle modifications and street-rod pathways distinct. */
final class RegulatoryTaxonomy
{
    /** @var array<string,string> */
    public const VEHICLES = [
        'car' => 'Cars', '4wd' => '4WDs & off-road vehicles',
        'light-truck' => 'Utes & light trucks', 'heavy-vehicle' => 'Heavy vehicles',
        'motorcycle' => 'Motorcycles', 'trailer' => 'Trailers',
        'street-rod' => 'Street rods & hot rods',
    ];

    /** @var array<string,string> */
    public const KINDS = [
        'roadworthiness' => 'Roadworthy requirements',
        'inspection_manual' => 'Inspection manuals',
        'modifications' => 'Ordinary vehicle modification rules',
        'code_of_practice' => 'Codes of practice',
        'design_rules' => 'Design rules',
        'street_rods' => 'Street rod construction & approval',
        'towing' => 'Towing rules',
        'trailer_construction' => 'Trailer construction',
        'load_restraint' => 'Load restraint',
        'registration' => 'Registration',
    ];

    /** @return array<string,string> */
    public static function vehiclesForBrand(string $brandId): array
    {
        $keys = match ($brandId) {
            'vanassist' => ['car', 'light-truck', 'trailer'],
            'towsmart' => ['car', '4wd', 'light-truck', 'heavy-vehicle', 'trailer'],
            'trailerwise' => ['light-truck', 'trailer'],
            default => array_keys(self::VEHICLES),
        };

        return array_intersect_key(self::VEHICLES, array_flip($keys));
    }

    /** @return array<string,string> */
    public static function kindsForBrand(string $brandId): array
    {
        $keys = match ($brandId) {
            'vanassist' => ['roadworthiness', 'inspection_manual', 'modifications', 'code_of_practice', 'design_rules', 'towing', 'trailer_construction', 'load_restraint', 'registration'],
            'towsmart' => ['inspection_manual', 'code_of_practice', 'design_rules', 'towing', 'trailer_construction', 'load_restraint', 'registration'],
            'trailerwise' => ['roadworthiness', 'inspection_manual', 'modifications', 'code_of_practice', 'design_rules', 'towing', 'trailer_construction', 'load_restraint', 'registration'],
            default => array_keys(self::KINDS),
        };

        return array_intersect_key(self::KINDS, array_flip($keys));
    }

    /** @return array{vehicle:string,kind:string} */
    public static function normalize(string $vehicle, string $kind): array
    {
        if ($vehicle === 'street-rod' && $kind === 'modifications') {
            $kind = 'street_rods';
        }
        if ($kind === 'street_rods') {
            $vehicle = 'street-rod';
        }

        return ['vehicle' => $vehicle, 'kind' => $kind];
    }
}
