<?php

declare(strict_types=1);

return [
    /** Default straight-line radius around the town centre for the "This town" filter. */
    'default_town_radius_km' => (int) env('GEO_DEFAULT_TOWN_RADIUS_KM', 20),
];
