<?php

declare(strict_types=1);

namespace App\Services\Search;

/** Routes structured selections to the catalogue that actually owns the data. */
final class StructuredSearchDestination
{
    /** @var array<string,string> */
    private const FACILITY_QUERIES = [
        'dump-points' => 'Dump points',
        'potable-water-refill' => 'Potable water refill',
        'rest-areas-and-rv-friendly-parking' => 'Rest areas and RV-friendly parking',
    ];

    /** @var list<string> */
    private const STAY_CATEGORIES = [
        'caravan-parks-and-campgrounds',
        'free-and-low-cost-camps',
    ];

    public static function path(
        string $categorySlug,
        string $location,
        ?float $latitude,
        ?float $longitude,
        bool $askEnabled,
    ): ?string {
        $location = trim($location);
        $hasCoordinates = self::validCoordinates($latitude, $longitude);

        if (in_array($categorySlug, self::STAY_CATEGORIES, true)) {
            $query = array_filter([
                'location' => $location !== '' ? $location : null,
                'lat' => $location === '' && $hasCoordinates ? $latitude : null,
                'lng' => $location === '' && $hasCoordinates ? $longitude : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            return 'stays' . ($query !== [] ? '?' . http_build_query($query) : '');
        }

        if (!$askEnabled || !isset(self::FACILITY_QUERIES[$categorySlug])) {
            return null;
        }

        $place = $location !== '' ? ' near ' . $location : ' near me';
        $query = array_filter([
            'q' => self::FACILITY_QUERIES[$categorySlug] . $place,
            'lat' => $location === '' && $hasCoordinates ? $latitude : null,
            'lng' => $location === '' && $hasCoordinates ? $longitude : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        return 'ask?' . http_build_query($query);
    }

    private static function validCoordinates(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null && $longitude !== null
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }
}
