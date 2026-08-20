<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiLocationService;

/**
 * Read-only location taxonomy (Increment I).
 */
final class LocationController extends Controller
{
    public function states(Request $request): Response
    {
        $result = (new AdminApiLocationService())->listStates($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function regions(Request $request): Response
    {
        $result = (new AdminApiLocationService())->listRegions($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function towns(Request $request): Response
    {
        $result = (new AdminApiLocationService())->listTowns($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }
}
