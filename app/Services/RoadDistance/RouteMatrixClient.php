<?php

declare(strict_types=1);

namespace App\Services\RoadDistance;

interface RouteMatrixClient
{
    public function enabled(): bool;

    public function maxDestinations(): int;

    /**
     * @param list<array{latitude:float,longitude:float}> $destinations
     * @return array<int,array{distance_meters:int,duration_seconds:int}>
     */
    public function routes(float $originLatitude, float $originLongitude, array $destinations): array;
}
