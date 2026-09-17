<?php

declare(strict_types=1);

return [
    /** Default straight-line radius around the town centre for the "This town" filter. */
    'default_town_radius_km' => (int) env('GEO_DEFAULT_TOWN_RADIUS_KM', 20),

    /**
     * When a service category is selected and the traveller did not set a
     * distance, expand through these radii until min results (VAN-011).
     *
     * @var list<int>
     */
    'provider_search_radius_ladder_km' => [25, 75, 150, 300],

    /** Stop expanding once at least this many providers are found. */
    'provider_search_min_results' => 3,
];
