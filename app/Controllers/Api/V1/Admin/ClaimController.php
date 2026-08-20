<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiClaimService;

final class ClaimController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiClaimService())->list($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Claim not found.');
        }

        return AdminApiEnvelope::data((new AdminApiClaimService())->show($id, $request));
    }

    public function approve(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Claim not found.');
        }

        return AdminApiEnvelope::data((new AdminApiClaimService())->approve($id, $request));
    }

    public function reject(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Claim not found.');
        }

        return AdminApiEnvelope::data((new AdminApiClaimService())->reject($id, $request->all(), $request));
    }

    public function requestEvidence(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Claim not found.');
        }

        return AdminApiEnvelope::data((new AdminApiClaimService())->requestEvidence($id, $request->all(), $request));
    }
}
