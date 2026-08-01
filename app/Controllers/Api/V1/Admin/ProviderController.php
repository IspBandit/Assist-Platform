<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiProviderService;
use App\Services\Api\AdminApiProviderWriteService;

final class ProviderController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiProviderService())->list($request);

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
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data((new AdminApiProviderService())->show($id));
    }

    public function store(Request $request): Response
    {
        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->create($request->all(), $request),
            201
        );
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->patch($id, $request->all(), $request)
        );
    }

    public function publish(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->publish($id, $request)
        );
    }

    public function unpublish(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->unpublish($id, $request)
        );
    }

    public function archive(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->archive($id, $request)
        );
    }

    public function restore(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->restore($id, $request)
        );
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Provider not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiProviderWriteService())->softDelete(
                $id,
                (string) $request->input('reason', ''),
                $request
            )
        );
    }
}
