<?php

declare(strict_types=1);

namespace App\Services;

final class MotorsportCatalogue
{
    /** @var array<string,string> */
    public const JURISDICTIONS = [
        'AUS' => 'Australia-wide',
        'ACT' => 'Australian Capital Territory',
        'NSW' => 'New South Wales',
        'VIC' => 'Victoria',
        'QLD' => 'Queensland',
        'SA' => 'South Australia',
        'WA' => 'Western Australia',
        'TAS' => 'Tasmania',
        'NT' => 'Northern Territory',
    ];

    /** @var array<string,string> */
    public const RULE_TYPES = [
        'competition' => 'Competition & sporting rules',
        'technical' => 'Vehicle & technical rules',
        'safety' => 'Safety, apparel & circuit rules',
        'licensing' => 'Licences, permits & log books',
        'state' => 'State & regional regulations',
        'event' => 'Event supplementary regulations',
    ];

    /**
     * The public taxonomy is deliberately explicit. Similar disciplines remain
     * separate because their licences, vehicle classes and technical books can differ.
     *
     * @var array<string,array{name:string,description:string,disciplines:array<string,string>}>
     */
    public const FAMILIES = [
        'circuit' => [
            'name' => 'Circuit, track & historic',
            'description' => 'Wheel-to-wheel racing, timed circuit activity, historic competition and emerging formats.',
            'disciplines' => [
                'circuit-racing' => 'Circuit racing',
                'regularity' => 'Regularity trials',
                'supersprint' => 'Supersprint',
                'superkart' => 'Superkart',
                'track-day-test-tune' => 'Track day, practice & test-and-tune',
                'historic-circuit' => 'Historic circuit competition',
                'electric-vehicle' => 'Electric vehicle competition',
                'esports' => 'Motorsport esports',
            ],
        ],
        'rally-road' => [
            'name' => 'Rally & road events',
            'description' => 'Closed-road, forest, tarmac, cross-country and navigation-based competition.',
            'disciplines' => [
                'rally' => 'Rally',
                'rallysprint' => 'Rallysprint',
                'tarmac-rally' => 'Tarmac rally',
                'cross-country-rally' => 'Cross-country rally',
                'rallycross' => 'Rallycross',
                'touring-navigation' => 'Touring, navigational assembly & road events',
            ],
        ],
        'off-road' => [
            'name' => 'Off-road competition',
            'description' => 'Desert, bush, stadium, side-by-side and off-road kart competition.',
            'disciplines' => [
                'off-road-racing' => 'Off-road racing',
                'stadium-off-road' => 'Stadium off-road',
                'side-by-side' => 'Side-by-side / SxS',
                'off-road-kart' => 'Off-road kart',
            ],
        ],
        'speed-drift' => [
            'name' => 'Speed, drift & handling',
            'description' => 'Timed speed events and judged or paired vehicle-control disciplines.',
            'disciplines' => [
                'hillclimb' => 'Hillclimb',
                'sprint' => 'Sprint',
                'autocross' => 'Autocross',
                'drift' => 'Drifting',
                'roll-racing' => 'Roll racing',
            ],
        ],
        'auto-test' => [
            'name' => 'Auto tests & participation events',
            'description' => 'Precision, club-entry and controlled vehicle demonstration formats.',
            'disciplines' => [
                'motorkhana' => 'Motorkhana',
                'khanacross' => 'Khanacross',
                'observed-section-trial' => 'Observed section trial',
                'driftkhana' => 'Driftkhana',
                'burnout' => 'Burnout competition',
                'go-to-whoa' => 'Go-to-whoa',
                'dyno' => 'Dyno competition',
                'show-shine' => 'Show & shine',
            ],
        ],
        'drag' => [
            'name' => 'Drag racing',
            'description' => 'Car, motorcycle and junior drag competition, including technical and licensing requirements.',
            'disciplines' => [
                'drag-racing-cars' => 'Drag racing — cars',
                'drag-racing-motorcycles' => 'Drag racing — motorcycles',
                'junior-drag' => 'Junior dragster & junior drag bike',
            ],
        ],
        'speedway' => [
            'name' => 'Speedway & oval',
            'description' => 'National racing rules plus the separate technical specifications issued for each division.',
            'disciplines' => [
                'speedway-oval' => 'Speedway oval racing',
                'sprintcars' => 'Sprintcars',
                'speedcars-midgets' => 'Speedcars / midgets',
                'sedans-stock-cars' => 'Sedans & stock cars',
                'modifieds' => 'Modifieds',
                'wingless' => 'Wingless competition',
                'demolition-derby' => 'Demolition derby',
            ],
        ],
        'karting' => [
            'name' => 'Karting',
            'description' => 'Karting Australia competition, state, class, homologation, circuit and safety rules.',
            'disciplines' => [
                'sprint-karting' => 'Sprint karting',
                'endurance-karting' => 'Endurance karting',
            ],
        ],
        'motorcycle' => [
            'name' => 'Motorcycle sport',
            'description' => 'The disciplines governed through Motorcycling Australia’s General Competition Rules.',
            'disciplines' => [
                'motorcycle-road-race' => 'Road race',
                'motorcycle-historic-road-race' => 'Historic road race',
                'motocross' => 'Motocross',
                'supercross' => 'Supercross',
                'classic-motocross' => 'Classic motocross',
                'classic-dirt-track' => 'Classic dirt track',
                'enduro' => 'Enduro',
                'reliability-trials' => 'Reliability trials',
                'atv' => 'ATV competition',
                'motorcycle-speedway' => 'Motorcycle speedway',
                'dirt-track-track' => 'Dirt track & track',
                'supermoto' => 'Supermoto',
                'motorcycle-trial' => 'Motorcycle trial',
                'minikhana' => 'Minikhana',
                'electric-motorcycle' => 'Electric motorcycle competition',
            ],
        ],
    ];

    /** @return array<string,string> */
    public static function disciplines(): array
    {
        $disciplines = [];
        foreach (self::FAMILIES as $family) {
            $disciplines += $family['disciplines'];
        }

        return $disciplines;
    }

    public static function familyFor(string $discipline): string
    {
        foreach (self::FAMILIES as $key => $family) {
            if (isset($family['disciplines'][$discipline])) {
                return $key;
            }
        }

        return '';
    }
}
