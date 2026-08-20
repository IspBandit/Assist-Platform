<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiSearchAnalyticsService;

final class SearchAnalyticsController extends Controller
{
    public function searches(Request $request): Response
    {
        $result = (new AdminApiSearchAnalyticsService())->searches($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function searchIntents(Request $request): Response
    {
        $result = (new AdminApiSearchAnalyticsService())->searchIntents($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function searchResultsPerformance(Request $request): Response
    {
        $result = (new AdminApiSearchAnalyticsService())->searchResultsPerformance($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }
}
