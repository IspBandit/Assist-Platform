<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiIdempotency;
use App\Services\Api\AdminApiRecycleBinService;

final class RecycleBinController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiRecycleBinService())->list($request);

        return AdminApiEnvelope::collection(
            $result['items'],
            $result['meta'],
            $result['links']
        );
    }

    public function show(Request $request): Response
    {
        $entityType = (string) $request->route('entity_type', '');
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Recycle bin item not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiRecycleBinService())->show($entityType, $id)
        );
    }

    public function restore(Request $request): Response
    {
        $entityType = (string) $request->route('entity_type', '');
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Recycle bin item not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiRecycleBinService())->restore($entityType, $id, $request)
        );
    }

    public function purge(Request $request): Response
    {
        $entityType = (string) $request->route('entity_type', '');
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Recycle bin item not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiRecycleBinService())->purge($entityType, $id, $request->all(), $request)
        );
    }

    public function bulkRestore(Request $request): Response
    {
        $key = AdminApiIdempotency::requireKey($request);
        $execution = AdminApiIdempotency::execute(
            'recycle_bin:bulk_restore',
            $key,
            fn (): array => (new AdminApiRecycleBinService())->bulkRestore($request->all(), $request)
        );

        return AdminApiEnvelope::data($execution['result'], $execution['replay'] ? 200 : 200);
    }

    public function bulkPurge(Request $request): Response
    {
        $key = AdminApiIdempotency::requireKey($request);
        $execution = AdminApiIdempotency::execute(
            'recycle_bin:bulk_purge',
            $key,
            fn (): array => (new AdminApiRecycleBinService())->bulkPurge($request->all(), $request)
        );

        return AdminApiEnvelope::data($execution['result'], $execution['replay'] ? 200 : 200);
    }
}
