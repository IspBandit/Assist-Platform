<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Exceptions\AdminApiException;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiDraftService;
use App\Services\Api\AdminApiEnvelope;

final class DraftController extends Controller
{
    public function index(Request $request): Response
    {
        $result = (new AdminApiDraftService())->list($request);

        return AdminApiEnvelope::collection(
            $result['items'],
            $result['meta'],
            $result['links']
        );
    }

    public function show(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Draft not found.');
        }

        return AdminApiEnvelope::data((new AdminApiDraftService())->show($id));
    }

    public function store(Request $request): Response
    {
        return AdminApiEnvelope::data(
            (new AdminApiDraftService())->create($request->all(), $request),
            201
        );
    }

    public function update(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Draft not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiDraftService())->patch($id, $request->all(), $request)
        );
    }

    public function approve(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Draft not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiDraftService())->approve($id, $request)
        );
    }

    public function reject(Request $request): Response
    {
        $id = trim((string) $request->route('id', ''));
        if ($id === '') {
            throw new AdminApiException(404, 'not_found', 'Draft not found.');
        }

        return AdminApiEnvelope::data(
            (new AdminApiDraftService())->reject($id, $request->all(), $request)
        );
    }
}
