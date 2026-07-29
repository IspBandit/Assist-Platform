<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\QldCoverageReportService;

/** Read-only Queensland coverage matrix review (offline artefacts). */
final class QldCoverageController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('data_sources.view');
        if (!auth()->isSuperAdmin() && !auth()->hasAnyRole('administrator', 'platform-administrator')) {
            $this->abort(403);
        }

        $service = new QldCoverageReportService();
        return $this->view('admin.qld-coverage.index', [
            'title' => 'Queensland coverage',
            'summary' => $service->summary(),
            'batches' => $service->batches(),
            'zeroSample' => $service->zeroCoverageSample(50),
        ]);
    }
}
