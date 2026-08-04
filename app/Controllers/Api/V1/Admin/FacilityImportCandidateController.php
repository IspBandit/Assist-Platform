<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiImportCandidateService;

/**
 * Facility import-candidate review queue (Option B Increment H / H.1).
 *
 * Distinct from GET /imports (RIC package jobs). Approve/reject are human-only.
 */
final class FacilityImportCandidateController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiImportCandidateService())->listFacilityCandidates($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Facility import candidate not found.');
        }

        return AdminApiEnvelope::data((new AdminApiImportCandidateService())->showFacilityCandidate($id));
    }

    public function approve(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Facility import candidate not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiImportCandidateService())->approveFacilityCandidate($id, $request->all(), $request)
        );
    }

    public function reject(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Facility import candidate not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiImportCandidateService())->rejectFacilityCandidate($id, $request->all(), $request)
        );
    }
}
