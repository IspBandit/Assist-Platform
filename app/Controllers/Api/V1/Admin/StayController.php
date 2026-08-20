<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiStayService;
use App\Services\Api\AdminApiStayWriteService;

final class StayController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiStayService())->list($request);

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
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data((new AdminApiStayService())->show($id));
    }

    public function store(Request $request): Response
    {
        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->create($request->all(), $request),
            201
        );
    }

    public function update(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->patch($id, $request->all(), $request)
        );
    }

    public function publish(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->publish($id, $request)
        );
    }

    public function unpublish(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->unpublish($id, $request)
        );
    }

    public function archive(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->archive($id, $request)
        );
    }

    public function restore(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->restore($id, $request)
        );
    }

    public function destroy(Request $request): Response
    {
        $id = (int) $request->route('id', 0);
        if ($id < 1) {
            throw new AdminApiException(404, 'not_found', 'Stay not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiStayWriteService())->softDelete(
                $id,
                (string) $request->input('reason', ''),
                $request
            )
        );
    }
}
