<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiFacilityImportService;

final class FacilityImportController extends Controller
{
    public function store(Request $request): Response
    {
        return AdminApiEnvelope::data(
            (new AdminApiFacilityImportService())->create($request->all(), $request),
            201
        );
    }
}
