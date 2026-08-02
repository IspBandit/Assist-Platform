<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiSyncConflictService;

final class SyncConflictController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiSyncConflictService())->list($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function show(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Sync conflict not found.');
        }

        return AdminApiEnvelope::data((new AdminApiSyncConflictService())->show($id));
    }

    public function resolve(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Sync conflict not found.');
        }

        return AdminApiEnvelope::data((new AdminApiSyncConflictService())->resolve($id, $request->all(), $request));
    }
}
