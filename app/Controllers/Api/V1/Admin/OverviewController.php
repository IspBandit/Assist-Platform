<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiOverviewService;

/**
 * Overview and website insights for Assist RIC everyday management.
 */
final class OverviewController extends Controller
{
    public function overview(Request $request): Response
    {
        return AdminApiEnvelope::data((new AdminApiOverviewService())->overview($request));
    }

    public function websiteInsights(Request $request): Response
    {
        return AdminApiEnvelope::data((new AdminApiOverviewService())->websiteInsights($request));
    }
}
