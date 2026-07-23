<?php

declare(strict_types=1);

namespace App\Services;

final class DirectoryPresentation
{
    /** @return array{eyebrow:string,heading:string,intro:string,search_placeholder:string} */
    public static function copyFor(string $brandId): array
    {
        return match ($brandId) {
            'towsmart' => [
                'eyebrow' => 'Towing specialist directory',
                'heading' => 'Find the right towing specialist',
                'intro' => 'Search weighing services, towbar and brake specialists, suspension experts, trainers and other towing professionals.',
                'search_placeholder' => 'Business or specialist service',
            ],
            'trailerwise' => [
                'eyebrow' => 'Trailer service network',
                'heading' => 'Find a trailer specialist',
                'intro' => 'Search repairers, inspectors, parts suppliers, manufacturers and specialist trailer businesses across Australia.',
                'search_placeholder' => 'Business or trailer service',
            ],
            default => [
                'eyebrow' => 'Caravan and RV service network',
                'heading' => 'Find caravan and RV help',
                'intro' => 'Search relevant mobile and workshop providers by service and location, then check their details before making contact.',
                'search_placeholder' => 'Business name',
            ],
        };
    }
}
