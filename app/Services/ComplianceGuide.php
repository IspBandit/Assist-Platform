<?php

declare(strict_types=1);

namespace App\Services;

final class ComplianceGuide
{
    /** @var array<string,string> */
    public const JURISDICTIONS = [
        'ACT' => 'Australian Capital Territory', 'NSW' => 'New South Wales',
        'NT' => 'Northern Territory', 'QLD' => 'Queensland', 'SA' => 'South Australia',
        'TAS' => 'Tasmania', 'VIC' => 'Victoria', 'WA' => 'Western Australia',
    ];

    /** @var array<string,string> */
    public const VEHICLES = [
        'car' => 'Car', '4wd' => '4WD or off-road vehicle', 'light-truck' => 'Ute or light truck',
        'heavy-vehicle' => 'Heavy vehicle', 'motorcycle' => 'Motorcycle',
        'trailer' => 'Trailer or caravan', 'street-rod' => 'Street rod or hot rod',
    ];

    /** @var array<string,string> */
    public const INTENTIONS = [
        'understand' => 'Understand what applies', 'inspect' => 'Prepare for inspection',
        'modify' => 'Plan a modification', 'register' => 'Register or transfer',
        'tow' => 'Check a towing combination', 'travel' => 'Prepare for interstate travel',
    ];

    /** @return array{jurisdiction:string,vehicle:string,intention:string,kind:string}|null */
    public static function selections(string $jurisdiction, string $vehicle, string $intention): ?array
    {
        if (!isset(self::JURISDICTIONS[$jurisdiction], self::VEHICLES[$vehicle], self::INTENTIONS[$intention])) {
            return null;
        }
        return [
            'jurisdiction' => $jurisdiction,
            'vehicle' => $vehicle,
            'intention' => $intention,
            'kind' => self::documentKind($intention),
        ];
    }

    public static function documentKind(string $intention): string
    {
        return match ($intention) {
            'inspect' => 'roadworthiness',
            'modify' => 'modifications',
            'register' => 'registration',
            'tow', 'travel' => 'towing',
            default => '',
        };
    }

    /** @return array<int,array{title:string,body:string}> */
    public static function steps(string $intention): array
    {
        $middle = match ($intention) {
            'inspect' => ['title' => 'Prepare the exact vehicle', 'body' => 'Use the authority inspection material to identify evidence, safety items and any jurisdiction-specific booking requirement.'],
            'modify' => ['title' => 'Confirm approval before work starts', 'body' => 'Discuss the exact modification with an appropriately qualified specialist or approved engineer before purchasing parts or beginning work.'],
            'register' => ['title' => 'Check evidence and appointment rules', 'body' => 'Confirm identity, ownership, inspection and supporting-document requirements with the registering authority.'],
            'tow' => ['title' => 'Verify every plated limit', 'body' => 'Use the exact tow vehicle, towbar, trailer and tyre ratings, then measure the loaded combination rather than relying on advertised figures.'],
            'travel' => ['title' => 'Check each jurisdiction on the route', 'body' => 'Registration may start in one state, but road, towing and access rules can change as the journey crosses borders.'],
            default => ['title' => 'Read the official scope', 'body' => 'Check whether the source applies to the vehicle class, date, use and jurisdiction in question.'],
        };

        return [
            ['title' => 'Start with the official source', 'body' => 'Open the current government page or downloadable instrument shown below and confirm its version and effective date.'],
            $middle,
            ['title' => 'Keep an evidence trail', 'body' => 'Save the outcome to My Garage and retain approvals, reports and receipts in the private document wallet.'],
            ['title' => 'Use a relevant specialist when needed', 'body' => 'Provider results are separate from official sources. Verified capability labels identify reviewed evidence, not government endorsement.'],
        ];
    }

    public static function limitation(): string
    {
        return 'This guide organises official sources and practical next steps. It is not legal, engineering or roadworthy approval and cannot account for every vehicle, modification or later source change.';
    }
}
