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
        $batch = trim((string) $request->query('batch', ''));
        $town = trim((string) $request->query('town', ''));
        $category = trim((string) $request->query('category', ''));
        $status = trim((string) $request->query('status', ''));
        $source = trim((string) $request->query('source', 'zero'));
        if (!in_array($source, ['zero', 'weak'], true)) {
            $source = 'zero';
        }

        $filters = [
            'batch' => $batch,
            'town' => $town,
            'category' => $category,
            'status' => $status,
            'source' => $source,
            'limit' => 50,
        ];

        return $this->view('admin.qld-coverage.index', [
            'title' => 'Queensland coverage',
            'summary' => $service->summary(),
            'batches' => $service->batches(),
            'filters' => $filters,
            'coverageSample' => $service->coverageRows($filters),
            'reviewSample' => $service->reviewCandidates([
                'batch' => $batch,
                'town' => $town,
                'category' => $category,
                'limit' => 15,
            ]),
            'duplicates' => $service->possibleDuplicates(25),
            'regulated' => $service->regulatedMissingLicence(25),
        ]);
    }
}
