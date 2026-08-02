<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiDuplicateService;
use App\Services\Api\AdminApiEnvelope;

final class DuplicateController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiDuplicateService())->list($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function show(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDuplicateService())->show($id));
    }

    public function check(Request $request): Response
    {
        return AdminApiEnvelope::data(
            (new AdminApiDuplicateService())->check($request->all(), $request),
            201
        );
    }

    public function merge(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDuplicateService())->merge($id, $request->all(), $request));
    }

    public function notDuplicate(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDuplicateService())->markNotDuplicate($id, $request));
    }

    public function defer(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Duplicate decision not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDuplicateService())->defer($id, $request));
    }

    public function mergeHistory(Request $request): Response
    {
        $result = (new AdminApiDuplicateService())->mergeHistory($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }
}
