<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiFeatureFlagService;

final class FeatureFlagController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiFeatureFlagService())->list();

        return AdminApiEnvelope::collection($result['items'], $result['meta'], []);
    }
}
