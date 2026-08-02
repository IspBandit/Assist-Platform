<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\GovernmentDatasetService;
use RuntimeException;
use Throwable;

/**
 * DATA-012 government dataset catalogue and facility candidate review.
 */
final class GovernmentDatasetsController extends Controller
{
    private GovernmentDatasetService $datasets;

    public function __construct()
    {
        $this->datasets = new GovernmentDatasetService();
    }

    public function index(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.view');
        return $this->view('admin.data-sources.datasets', [
            'title' => 'Government datasets',
            'datasets' => $this->datasets->listDatasets(),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.manage');
        try {
            $this->datasets->saveDataset(
                (int) $request->input('id'),
                $request->input('is_enabled') === '1',
                auth()->id()
            );
            return $this->redirectWith('/admin/data-sources/datasets', 'success', 'Dataset settings saved.');
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/data-sources/datasets', 'error', $e->getMessage());
        }
    }

    public function edit(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.manage');
        $id = (int) $request->query('id', 0);
        $dataset = $id > 0 ? $this->datasets->findDataset($id) : null;
        if ($id > 0 && $dataset === null) {
            return $this->redirectWith('/admin/data-sources/datasets', 'error', 'Dataset not found.');
        }
        $settings = [];
        if ($dataset !== null && !empty($dataset['settings_json'])) {
            $decoded = json_decode((string) $dataset['settings_json'], true);
            if (is_array($decoded)) {
                $settings = $decoded;
            }
        }
        return $this->view('admin.data-sources.dataset-edit', [
            'title' => $dataset ? 'Edit government dataset' : 'Add government dataset',
            'dataset' => $dataset,
            'settings' => $settings,
        ]);
    }

    public function upsert(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.manage');
        try {
            $id = $this->datasets->upsertCatalogue([
                'id' => (int) $request->input('id'),
                'dataset_key' => (string) $request->input('dataset_key'),
                'publisher' => (string) $request->input('publisher'),
                'title' => (string) $request->input('title'),
                'coverage' => (string) $request->input('coverage'),
                'jurisdiction' => (string) $request->input('jurisdiction'),
                'record_types' => (string) $request->input('record_types'),
                'licence' => (string) $request->input('licence'),
                'attribution' => (string) $request->input('attribution'),
                'trust_policy' => (string) $request->input('trust_policy', 'trusted_review'),
                'fetch_method' => (string) $request->input('fetch_method'),
                'source_format' => (string) $request->input('source_format'),
                'update_frequency' => (string) $request->input('update_frequency'),
                'connector_key' => (string) $request->input('connector_key'),
                'endpoint_url' => (string) $request->input('endpoint_url'),
                'source_url' => (string) $request->input('source_url'),
                'default_facility_type' => (string) $request->input('default_facility_type', 'other_essential'),
                'is_enabled' => $request->input('is_enabled') === '1',
                'auto_update_enabled' => $request->input('auto_update_enabled') === '1',
                'catalogue_status' => (string) $request->input('catalogue_status', 'planned'),
                'notes' => (string) $request->input('notes'),
                'duplicate_rules_json' => trim((string) $request->input('duplicate_rules_json', '')),
                'package_api_url' => (string) $request->input('package_api_url'),
                'resource_id' => (string) $request->input('resource_id'),
                'resource_url' => (string) $request->input('resource_url'),
                'feature_url' => (string) $request->input('feature_url'),
                'name_field' => (string) $request->input('name_field'),
                'id_field' => (string) $request->input('id_field'),
                'lat_field' => (string) $request->input('lat_field'),
                'lng_field' => (string) $request->input('lng_field'),
                'address_field' => (string) $request->input('address_field'),
                'type_field' => (string) $request->input('type_field'),
                'filter_field' => (string) $request->input('filter_field'),
                'filter_value' => (string) $request->input('filter_value'),
                'format' => (string) $request->input('format'),
                'limit' => (int) $request->input('limit', 100),
                'settings_json' => trim((string) $request->input('settings_json', '')),
            ], auth()->id());
            return $this->redirectWith('/admin/data-sources/datasets', 'success', 'Catalogue row #' . $id . ' saved.');
        } catch (Throwable $e) {
            $back = (int) $request->input('id') > 0
                ? '/admin/data-sources/datasets/edit?id=' . (int) $request->input('id')
                : '/admin/data-sources/datasets/edit';
            return $this->redirectWith($back, 'error', $e->getMessage());
        }
    }

    public function fetch(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.run');
        try {
            $id = (int) $request->input('id');
            $brandId = current_brand()->databaseId();
            $result = $request->input('use_fixture') === '1'
                ? $this->datasets->importFixture($id, $brandId, auth()->id())
                : $this->datasets->fetchDataset($id, $brandId, auth()->id());
            return $this->redirectWith(
                '/admin/data-sources/facilities/review',
                'success',
                'Import job #' . $result['job_id'] . ': ' . $result['new'] . ' new candidate(s) of ' . $result['found'] . ' row(s).'
            );
        } catch (Throwable $e) {
            return $this->redirectWith('/admin/data-sources/datasets', 'error', $e->getMessage());
        }
    }

    public function facilityReview(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        return $this->view('admin.data-sources.facility-review', [
            'title' => 'Traveller facility import review',
            'candidates' => $this->datasets->pendingCandidates(),
        ]);
    }

    public function reviewFacility(Request $request): Response
    {
        $this->requirePlatformAdmin('data_sources.review');
        try {
            $action = (string) $request->input('action');
            $notes = trim((string) $request->input('notes', '')) ?: null;
            $bulk = $request->input('candidate_ids');
            if (is_array($bulk) && $bulk !== []) {
                $ids = array_map('intval', $bulk);
                $result = $this->datasets->reviewCandidates($ids, $action, auth()->id(), $notes);
                $msg = $result['processed'] . ' candidate(s) updated.';
                if ($result['errors'] !== []) {
                    $msg .= ' ' . count($result['errors']) . ' error(s).';
                }
                return $this->redirectWith('/admin/data-sources/facilities/review', $result['errors'] === [] ? 'success' : 'error', $msg);
            }
            $this->datasets->reviewCandidate(
                (int) $request->input('id'),
                $action,
                (int) auth()->id(),
                $notes
            );
            return $this->redirectWith('/admin/data-sources/facilities/review', 'success', 'Facility candidate updated.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/admin/data-sources/facilities/review', 'error', $e->getMessage());
        }
    }

    private function requirePlatformAdmin(string $permission): void
    {
        $this->requirePermission($permission);
        if (!auth()->isSuperAdmin() && !auth()->hasAnyRole('administrator', 'platform-administrator')) {
            $this->abort(403);
        }
    }
}
