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

        // Traveller sentences in the town field (any location) belong on Ask —
        // structured /find only resolves town/postcode names.
        if ($askEnabled && self::looksLikeTravellerSentence($location)) {
            $query = array_filter([
                'q' => $location,
                'lat' => $hasCoordinates ? $latitude : null,
                'lng' => $hasCoordinates ? $longitude : null,
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            return 'ask?' . http_build_query($query);
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

    /**
     * True when the location field holds a natural-language request rather than
     * a town, suburb or postcode (affects every region, not one town).
     */
    public static function looksLikeTravellerSentence(string $location): bool
    {
        $location = trim((string) preg_replace('/\s+/u', ' ', $location));
        if ($location === '' || preg_match('/^\d{3,4}$/', $location) === 1) {
            return false;
        }

        $words = preg_split('/\s+/u', $location) ?: [];
        if (count($words) < 3) {
            return false;
        }

        if (preg_match(
            '/\b(need|needs|needing|looking|find a|find me|help|repair|repairs|repirs|fridge|refrigerat|broken|someone|where can|where do|near me|not working)\b/iu',
            $location
        ) === 1) {
            return true;
        }

        return count($words) >= 5
            && preg_match('/\b(near|around|in)\b/iu', $location) === 1;
    }

    private static function validCoordinates(?float $latitude, ?float $longitude): bool
    {
        return $latitude !== null && $longitude !== null
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;
    }
}
