<?php

declare(strict_types=1);

namespace App\Controllers\Api\V1\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Api\AdminApiEnvelope;
use App\Services\Api\AdminApiOpsService;

/**
 * Read-only ops failure queues (Increment I).
 */
final class OpsController extends Controller
{
    public function failedEmails(Request $request): Response
    {
        $result = (new AdminApiOpsService())->listFailedEmails($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }

    public function failedScheduledTasks(Request $request): Response
    {
        $result = (new AdminApiOpsService())->listFailedScheduledTasks($request);

        return AdminApiEnvelope::collection($result['items'], $result['meta'], $result['links']);
    }
}
