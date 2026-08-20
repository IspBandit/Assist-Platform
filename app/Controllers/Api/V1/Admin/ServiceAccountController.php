<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiServiceAccountService;

final class ServiceAccountController extends Controller
{
    public function index(Request $request): Response
    {
        return AdminApiEnvelope::collection((new AdminApiServiceAccountService())->list());
    }

    public function show(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        return AdminApiEnvelope::data((new AdminApiServiceAccountService())->get($id));
    }

    public function store(Request $request): Response
    {
        $body = $request->all();

        return AdminApiEnvelope::data(
            (new AdminApiServiceAccountService())->create($body, $request),
            201
        );
    }

    public function update(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiServiceAccountService())->update($id, $request->all(), $request)
        );
    }

    public function rotate(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiServiceAccountService())->rotate($id, $request)
        );
    }

    public function destroy(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Service account not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiServiceAccountService())->revoke($id, $request)
        );
    }
}
