<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiAuditService;
use App\Services\Api\AdminApiEnvelope;

final class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiAuditService())->list($request);

        return AdminApiEnvelope::collection(
            $result['items'],
            $result['meta'],
            $result['links']
        );
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Audit event not found.');
        }

        return AdminApiEnvelope::data((new AdminApiAuditService())->show($id));
    }
}
