<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiDatasetService;
use App\Services\Api\AdminApiEnvelope;

final class DatasetController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiDatasetService())->list($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function show(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Dataset not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDatasetService())->show($id));
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Dataset not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDatasetService())->patch($id, $request->all(), $request));
    }

    public function sync(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Dataset not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDatasetService())->enqueueSync($id, $request), 202);
    }

    public function syncHistory(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Dataset not found.');
        }

        $result = (new AdminApiDatasetService())->syncHistory($id, $request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }
}
