<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiImportService;

final class ImportController extends Controller
{
    public function store(Request $request): Response
    {
        return AdminApiEnvelope::data(
            (new AdminApiImportService())->create($request->all(), $request),
            201
        );
    }

    public function show(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Import job not found.');
        }

        return AdminApiEnvelope::data((new AdminApiImportService())->show($id));
    }

    public function validateJob(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Import job not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiImportService())->validate($id, $request)
        );
    }

    public function stage(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Import job not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiImportService())->stage($id, $request)
        );
    }
}
