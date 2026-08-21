<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiAiUsageService;
use App\Services\Api\AdminApiEnvelope;

final class AiUsageController extends Controller
{
    public function summary(Request $request): Response
    {
        return AdminApiEnvelope::data((new AdminApiAiUsageService())->summary($request));
    }

    public function costs(Request $request): Response
    {
        return AdminApiEnvelope::data((new AdminApiAiUsageService())->costs($request));
    }

    public function requests(Request $request): Response
    {
        $result = (new AdminApiAiUsageService())->requests($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function cachePerformance(Request $request): Response
    {
        return AdminApiEnvelope::data((new AdminApiAiUsageService())->cachePerformance($request));
    }
}
