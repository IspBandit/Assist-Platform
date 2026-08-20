<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiCategoryService;
use App\Services\Api\AdminApiEnvelope;

/**
 * Read-only brand provider categories (Increment I).
 */
final class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiCategoryService())->list($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }
}
