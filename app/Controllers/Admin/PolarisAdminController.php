<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\Polaris\CatalogueRepository;
use App\Services\Polaris\DealerClaimService;
use App\Services\Polaris\ImportService;
use App\Services\Polaris\ManufacturerClaimService;
use App\Services\Polaris\ManufacturerMergeService;
use RuntimeException;

final class PolarisAdminController extends Controller
{
    private CatalogueRepository $catalogue;
    private ImportService $imports;
    private ManufacturerClaimService $claims;
    private DealerClaimService $dealerClaims;
    private ManufacturerMergeService $merges;

    public function __construct()
    {
        $this->catalogue = new CatalogueRepository();
        $this->imports = new ImportService();
        $this->claims = new ManufacturerClaimService();
        $this->dealerClaims = new DealerClaimService();
        $this->merges = new ManufacturerMergeService();
    }

    public function index(Request $request): Response
    {
        $this->requirePolarisAdmin();
        $brandId = current_brand()->databaseId();
        return $this->view('admin.polaris.index', [
            'title' => 'Polaris catalogue',
            'counts' => $this->catalogue->adminCounts($brandId),
        ]);
    }

    public function manufacturers(Request $request): Response
    {
        $this->requirePolarisAdmin();
        $lifecycle = trim((string) $request->query('lifecycle', ''));
        return $this->view('admin.polaris.manufacturers', [
            'title' => 'Polaris manufacturers',
            'rows' => $this->catalogue->adminManufacturers(current_brand()->databaseId(), $lifecycle !== '' ? $lifecycle : null),
            'lifecycle' => $lifecycle,
        ]);
    }

    public function models(Request $request): Response
    {
        $this->requirePolarisAdmin();
        $lifecycle = trim((string) $request->query('lifecycle', ''));
        return $this->view('admin.polaris.models', [
            'title' => 'Polaris models',
            'rows' => $this->catalogue->adminModels(current_brand()->databaseId(), $lifecycle !== '' ? $lifecycle : null),
            'lifecycle' => $lifecycle,
        ]);
    }

    public function setModelLifecycle(Request $request): Response
    {
        $this->requirePermission('polaris.manage');
        $this->requirePolarisModule();
        $id = (int) $request->input('id');
        $lifecycle = (string) $request->input('lifecycle');
        $reason = trim((string) $request->input('reason', ''));
        $match = \App\Core\Database::selectOne(
            'SELECT * FROM polaris_rv_models WHERE id = ? AND brand_id = ? LIMIT 1',
            [$id, current_brand()->databaseId()]
        );
        if ($match === null) {
            $this->abort(404);
        }
        if (!$this->catalogue->setModelLifecycle(current_brand()->databaseId(), $id, $lifecycle, $reason !== '' ? $reason : null)) {
            $this->abort(422, 'Invalid lifecycle status.');
        }
        AuditLog::record(
            'polaris.model.lifecycle_changed',
            'polaris_rv_model',
            (string) $id,
            json_encode(['lifecycle_status' => $match['lifecycle_status']], JSON_THROW_ON_ERROR),
            json_encode(['lifecycle_status' => $lifecycle, 'reason' => $reason], JSON_THROW_ON_ERROR)
        );
        return $this->redirectWith('/admin/polaris/models', 'success', 'Model lifecycle updated.');
    }

    public function recycleBin(Request $request): Response
    {
        $this->requirePolarisAdmin();
        return $this->view('admin.polaris.recycle-bin', [
            'title' => 'Polaris recycle bin',
            'models' => $this->catalogue->adminModels(current_brand()->databaseId(), 'recycle_bin'),
            'manufacturers' => $this->catalogue->adminManufacturers(current_brand()->databaseId(), 'recycle_bin'),
        ]);
    }

    public function reviewQueue(Request $request): Response
    {
        $this->requirePolarisAdmin();
        $dealerClaims = [];
        try {
            $dealerClaims = $this->dealerClaims->pendingClaims(current_brand()->databaseId());
        } catch (\Throwable) {
            $dealerClaims = [];
        }
        return $this->view('admin.polaris.review-queue', [
            'title' => 'Polaris review queue',
            'drafts' => $this->imports->pendingDrafts(current_brand()->databaseId()),
            'claims' => $this->claims->pendingClaims(current_brand()->databaseId()),
            'dealerClaims' => $dealerClaims,
        ]);
    }

    public function imports(Request $request): Response
    {
        $this->requirePolarisAdmin();
        $brochureEnabled = \App\Services\FeatureFlag::enabled('polaris_brochure_extract', false);
        $aiImportEnabled = \App\Services\FeatureFlag::enabled('polaris_ai_import', false);
        return $this->view('admin.polaris.imports', [
            'title' => 'Polaris imports',
            'jobs' => $this->imports->jobs(current_brand()->databaseId()),
            'brochureEnabled' => $brochureEnabled,
            'aiImportEnabled' => $aiImportEnabled,
            'costDeterministic' => \App\Services\Polaris\ExtractionCostEstimator::forMode('brochure_text'),
            'costAi' => \App\Services\Polaris\ExtractionCostEstimator::forMode('ai_brochure', 4),
        ]);
    }

    public function uploadImport(Request $request): Response
    {
        $this->requirePermission('polaris.manage');
        $this->requirePolarisModule();
        $format = strtolower((string) $request->input('format', 'csv'));
        $userId = current_user() !== null ? (int) current_user()['id'] : null;
        $brandId = current_brand()->databaseId();

        try {
            if ($format === 'brochure') {
                if (!\App\Services\FeatureFlag::enabled('polaris_brochure_extract', false)) {
                    return $this->redirectWith('/admin/polaris/imports', 'error', 'Brochure extract is disabled (`polaris_brochure_extract`).');
                }
                $text = trim((string) $request->input('brochure_text', ''));
                $file = $_FILES['catalogue'] ?? null;
                $filename = 'brochure-paste.txt';
                if (is_array($file) && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                    $tmp = (string) ($file['tmp_name'] ?? '');
                    $binary = is_file($tmp) ? (string) file_get_contents($tmp) : '';
                    $filename = (string) ($file['name'] ?? $filename);
                    if (str_ends_with(strtolower($filename), '.pdf') || str_starts_with($binary, '%PDF')) {
                        $text = \App\Services\Polaris\BrochureTextExtractor::extractTextFromPdf($binary);
                        if ($text === '') {
                            return $this->redirectWith('/admin/polaris/imports', 'error', 'No extractable text layer found in PDF (OCR/AI not enabled).');
                        }
                    } else {
                        $text = $binary;
                    }
                }
                if ($text === '') {
                    return $this->redirectWith('/admin/polaris/imports', 'error', 'Paste brochure text or upload a .txt/.pdf with a text layer.');
                }
                $result = $this->imports->importBrochureText(
                    $brandId,
                    $text,
                    $userId,
                    $filename,
                    trim((string) $request->input('manufacturer_hint', '')) ?: null
                );
                $msg = 'Brochure draft job #' . $result['job_id'] . ' created (' . $result['draft_count'] . ' draft). '
                    . ($result['cost']['label'] ?? '');
                return $this->redirectWith('/admin/polaris/review-queue', 'success', $msg);
            }

            $file = $_FILES['catalogue'] ?? $_FILES['csv'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                return $this->redirectWith('/admin/polaris/imports', 'error', 'Catalogue upload failed.');
            }
            $tmp = (string) ($file['tmp_name'] ?? '');
            $name = (string) ($file['name'] ?? 'upload.csv');
            if ($format === '') {
                $lower = strtolower($name);
                $format = str_ends_with($lower, '.json') ? 'json'
                    : (str_ends_with($lower, '.xlsx') ? 'xlsx' : 'csv');
            }
            if ($format === 'xlsx') {
                $result = $this->imports->importXlsx($brandId, $tmp, $userId, $name);
            } else {
                $contents = is_file($tmp) ? (string) file_get_contents($tmp) : '';
                if ($contents === '') {
                    return $this->redirectWith('/admin/polaris/imports', 'error', 'Upload was empty.');
                }
                $result = $format === 'json'
                    ? $this->imports->importJson($brandId, $contents, $userId, $name)
                    : $this->imports->importCsv($brandId, $contents, $userId, $name);
            }
            $msg = 'Import job #' . $result['job_id'] . ' created with ' . $result['draft_count'] . ' draft(s).';
            if ($result['errors'] !== []) {
                $msg .= ' ' . count($result['errors']) . ' row error(s) recorded.';
            }
            return $this->redirectWith('/admin/polaris/review-queue', 'success', $msg);
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin/polaris/imports', 'error', $e->getMessage());
        }
    }

    public function mergeManufacturers(Request $request): Response
    {
        $this->requirePermission('polaris.manage');
        $this->requirePolarisModule();
        try {
            $this->merges->merge(
                current_brand()->databaseId(),
                (int) $request->input('survivor_id'),
                (int) $request->input('absorbed_id'),
                (int) current_user()['id'],
                trim((string) $request->input('notes', '')) ?: null
            );
            return $this->redirectWith('/admin/polaris/duplicates', 'success', 'Manufacturers merged. Absorbed profile moved to recycle bin.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin/polaris/duplicates', 'error', $e->getMessage());
        }
    }

    public function reviewDealerClaim(Request $request): Response
    {
        $this->requirePermission('polaris.manage');
        $this->requirePolarisModule();
        try {
            $this->dealerClaims->approveClaim(
                current_brand()->databaseId(),
                (int) $request->input('dealer_id'),
                (int) current_user()['id']
            );
            return $this->redirectWith('/admin/polaris/review-queue', 'success', 'Dealer claim approved.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin/polaris/review-queue', 'error', $e->getMessage());
        } catch (\Throwable) {
            return $this->redirectWith('/admin/polaris/review-queue', 'error', 'Dealer claim approval failed.');
        }
    }

    public function reviewDraft(Request $request): Response
    {
        $this->requirePermission('polaris.review');
        $this->requirePolarisModule();
        $id = (int) $request->input('id');
        $action = (string) $request->input('action');
        $notes = trim((string) $request->input('notes', ''));
        $userId = (int) current_user()['id'];
        try {
            if ($action === 'approve') {
                $this->imports->approveDraft(current_brand()->databaseId(), $id, $userId, $notes !== '' ? $notes : null);
                return $this->redirectWith('/admin/polaris/review-queue', 'success', 'Draft approved and published.');
            }
            if ($action === 'reject') {
                $this->imports->rejectDraft(current_brand()->databaseId(), $id, $userId, $notes !== '' ? $notes : null);
                return $this->redirectWith('/admin/polaris/review-queue', 'success', 'Draft rejected.');
            }
            $this->abort(422, 'Invalid review action.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin/polaris/review-queue', 'error', $e->getMessage());
        }
    }

    public function reviewClaim(Request $request): Response
    {
        $this->requirePermission('polaris.manage');
        $this->requirePolarisModule();
        $id = (int) $request->input('id');
        $action = (string) $request->input('action');
        $notes = trim((string) $request->input('notes', ''));
        $userId = (int) current_user()['id'];
        try {
            if ($action === 'approve') {
                $this->claims->approveClaim(current_brand()->databaseId(), $id, $userId, $notes !== '' ? $notes : null);
                return $this->redirectWith('/admin/polaris/review-queue', 'success', 'Manufacturer claim approved.');
            }
            if ($action === 'reject') {
                $this->claims->rejectClaim(current_brand()->databaseId(), $id, $userId, $notes !== '' ? $notes : null);
                return $this->redirectWith('/admin/polaris/review-queue', 'success', 'Manufacturer claim rejected.');
            }
            $this->abort(422, 'Invalid claim action.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin/polaris/review-queue', 'error', $e->getMessage());
        }
    }

    public function settings(Request $request): Response
    {
        $this->requirePolarisAdmin();
        return $this->view('admin.polaris.inventory', [
            'title' => 'Polaris settings',
            'section' => 'settings',
            'rows' => [],
            'note' => 'Brand remains private until Phase 9 release criteria pass. Feature flags and domain are managed in Platform Control Centre.',
        ]);
    }

    public function placeholder(Request $request): Response
    {
        $this->requirePolarisAdmin();
        $section = (string) $request->route('section');
        $brandId = current_brand()->databaseId();
        $rows = [];
        $note = 'Inventory view — deeper editing remains review-queue and model lifecycle driven.';
        if ($section === 'variants') {
            $rows = $this->catalogue->adminVariants($brandId);
            $note = 'Variant catalogue inventory.';
        } elseif ($section === 'floorplans') {
            $rows = $this->catalogue->adminFloorplans($brandId);
            $note = 'Floorplan inventory linked to models.';
        } elseif ($section === 'analytics') {
            $rows = $this->catalogue->adminAnalyticsSummary($brandId);
            $note = 'First-party Polaris analytics event counts (fail-closed).';
        } elseif ($section === 'duplicates') {
            $rows = $this->catalogue->adminManufacturers($brandId);
            $note = 'Merge duplicates carefully: models reassign to the survivor; absorbed manufacturer moves to recycle bin.';
            return $this->view('admin.polaris.duplicates', [
                'title' => 'Polaris duplicates',
                'section' => $section,
                'rows' => $rows,
                'note' => $note,
            ]);
        } elseif ($section === 'data-sources') {
            $rows = $this->catalogue->sourcesForBrand($brandId, 100);
            $note = 'Registered Polaris data sources / provenance.';
        } elseif ($section === 'claims') {
            $rows = $this->claims->pendingClaims($brandId);
            $note = 'Pending manufacturer claims (also on Review queue).';
        } elseif (!in_array($section, ['specifications', 'extraction-jobs', 'corrections'], true)) {
            $this->abort(404);
        } else {
            $note = ucfirst(str_replace('-', ' ', $section)) . ' tooling is planned; use imports and review queue for draft-first ingest.';
        }
        return $this->view('admin.polaris.inventory', [
            'title' => 'Polaris ' . str_replace('-', ' ', $section),
            'section' => $section,
            'rows' => $rows,
            'note' => $note,
        ]);
    }

    private function requirePolarisAdmin(): void
    {
        $this->requirePermission('polaris.manage');
        $this->requirePolarisModule();
    }

    private function requirePolarisModule(): void
    {
        if (!current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
    }
}
