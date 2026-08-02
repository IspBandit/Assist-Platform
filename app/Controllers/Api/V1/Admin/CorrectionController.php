<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiCorrectionService;
use App\Services\Api\AdminApiEnvelope;

final class CorrectionController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiCorrectionService())->list($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Correction not found.');
        }

        return AdminApiEnvelope::data((new AdminApiCorrectionService())->show($id));
    }

    public function approve(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Correction not found.');
        }

        return AdminApiEnvelope::data((new AdminApiCorrectionService())->approve($id, $request));
    }

    public function reject(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Correction not found.');
        }

        return AdminApiEnvelope::data((new AdminApiCorrectionService())->reject($id, $request->all(), $request));
    }
}
