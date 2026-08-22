<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditLog;
use App\Services\Polaris\AnalyticsService;
use App\Services\Polaris\CatalogueRepository;
use App\Services\Polaris\DuplicateDetection;
use App\Services\Polaris\ManufacturerClaimService;
use App\Services\Polaris\ManufacturerDataQualityService;
use App\Services\Polaris\ManufacturerPortalService;
use RuntimeException;

/**
 * Manufacturer claim-first portal for Polaris.
 */
final class ManufacturerPortalController extends Controller
{
    private ManufacturerClaimService $claims;
    private CatalogueRepository $catalogue;
    private ManufacturerPortalService $portal;
    private ManufacturerDataQualityService $dataQuality;

    public function __construct()
    {
        $this->claims = new ManufacturerClaimService();
        $this->catalogue = new CatalogueRepository();
        $this->portal = new ManufacturerPortalService();
        $this->dataQuality = new ManufacturerDataQualityService();
    }

    public function index(Request $request): Response
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/portal/manufacturer'));
        }
        $user = current_user();
        $claimed = $this->claims->claimedManufacturerForUser(current_brand()->databaseId(), (int) $user['id']);
        if ($claimed === null) {
            return $this->redirect('/portal/manufacturer/claims');
        }
        $models = $this->catalogue->modelsForManufacturer((int) $claimed['id']);
        AnalyticsService::track('manufacturer_portal_viewed', (int) $user['id'], 'manufacturer', (int) $claimed['id'], [], 'authenticated');

        return $this->view('polaris.portal.dashboard', [
            'title' => 'Manufacturer portal',
            'manufacturer' => $claimed,
            'models' => $models,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function claims(Request $request): Response
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/portal/manufacturer/claims'));
        }
        $q = trim((string) $request->query('q', ''));
        $matches = $this->claims->searchForClaim(current_brand()->databaseId(), $q);
        $duplicates = $q !== ''
            ? DuplicateDetection::findLikelyDuplicates($q, $matches, 75.0)
            : [];

        return $this->view('polaris.portal.claims', [
            'title' => 'Claim manufacturer profile',
            'query' => $q,
            'matches' => $matches,
            'duplicates' => $duplicates,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function submitClaim(Request $request): Response
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/portal/manufacturer/claims'));
        }
        $user = current_user();
        try {
            $id = $this->claims->submitClaim(
                current_brand()->databaseId(),
                (int) $request->input('manufacturer_id'),
                (int) $user['id'],
                trim((string) $request->input('contact_email', $user['email'] ?? '')),
                trim((string) $request->input('evidence', ''))
            );
            AnalyticsService::track('manufacturer_claim_started', (int) $user['id'], 'manufacturer_claim', $id, [], 'authenticated');
            return $this->redirectWith('/portal/manufacturer/claims', 'success', 'Claim submitted for review. Prefer claiming an existing profile over creating a duplicate.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/portal/manufacturer/claims', 'error', $e->getMessage());
        }
    }

    public function models(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $manufacturer = $gate;
        $models = Database::select(
            'SELECT * FROM polaris_rv_models WHERE manufacturer_id = ? AND deleted_at IS NULL ORDER BY name ASC',
            [(int) $manufacturer['id']]
        );
        return $this->view('polaris.portal.models', [
            'title' => 'Your models',
            'manufacturer' => $manufacturer,
            'models' => $models,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function editModel(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $manufacturer = $gate;
        $model = Database::selectOne(
            'SELECT * FROM polaris_rv_models WHERE id = ? AND manufacturer_id = ? AND deleted_at IS NULL LIMIT 1',
            [(int) $request->route('id'), (int) $manufacturer['id']]
        );
        if ($model === null) {
            $this->abort(404);
        }
        $variants = Database::select(
            'SELECT * FROM polaris_rv_variants WHERE model_id = ? AND deleted_at IS NULL ORDER BY name ASC',
            [(int) $model['id']]
        );
        return $this->view('polaris.portal.model-edit', [
            'title' => 'Edit ' . $model['name'],
            'manufacturer' => $manufacturer,
            'model' => $model,
            'variants' => $variants,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function saveModel(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $manufacturer = $gate;
        $id = (int) $request->input('id');
        $model = Database::selectOne(
            'SELECT * FROM polaris_rv_models WHERE id = ? AND manufacturer_id = ? AND deleted_at IS NULL LIMIT 1',
            [$id, (int) $manufacturer['id']]
        );
        if ($model === null) {
            $this->abort(404);
        }
        $description = trim((string) $request->input('description', ''));
        $production = (string) $request->input('production_status', $model['production_status']);
        if (!in_array($production, ['current', 'upcoming', 'superseded', 'discontinued'], true)) {
            $production = (string) $model['production_status'];
        }
        Database::affecting(
            'UPDATE polaris_rv_models SET description = ?, production_status = ?, verification_status = \'pending\', updated_at = NOW() WHERE id = ?',
            [$description, $production, $id]
        );
        AuditLog::record(
            'polaris.manufacturer.model_updated',
            'polaris_rv_model',
            (string) $id,
            json_encode(['description' => $model['description']], JSON_THROW_ON_ERROR),
            json_encode(['description' => $description, 'production_status' => $production], JSON_THROW_ON_ERROR)
        );
        return $this->redirectWith('/portal/manufacturer/models/' . $id, 'success', 'Model saved as pending verification.');
    }

    public function profile(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        return $this->view('polaris.portal.profile', [
            'title' => 'Manufacturer profile',
            'manufacturer' => $gate,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function saveProfile(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        try {
            $this->portal->updateProfile(
                current_brand()->databaseId(),
                (int) $gate['id'],
                [
                    'description' => $request->input('description'),
                    'website_url' => $request->input('website_url'),
                    'manufacturing_location' => $request->input('manufacturing_location'),
                    'warranty_summary' => $request->input('warranty_summary'),
                ],
                (int) current_user()['id']
            );
            return $this->redirectWith('/portal/manufacturer/profile', 'success', 'Profile saved for verification.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/portal/manufacturer/profile', 'error', $e->getMessage());
        } catch (\Throwable) {
            return $this->redirectWith('/portal/manufacturer/profile', 'error', 'Profile could not be saved.');
        }
    }

    public function media(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $rows = [];
        try {
            $rows = $this->portal->listMedia((int) $gate['id']);
        } catch (\Throwable) {
            $rows = [];
        }
        return $this->view('polaris.portal.media', [
            'title' => 'Media & brochures',
            'manufacturer' => $gate,
            'media' => $rows,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function uploadMedia(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $file = $_FILES['media'] ?? null;
        if (!is_array($file)) {
            return $this->redirectWith('/portal/manufacturer/media', 'error', 'No file uploaded.');
        }
        try {
            $this->portal->storeMedia(
                (int) $gate['id'],
                $file,
                (string) $request->input('media_type', 'other'),
                trim((string) $request->input('title', '')),
                (int) current_user()['id']
            );
            return $this->redirectWith('/portal/manufacturer/media', 'success', 'Media uploaded for review.');
        } catch (RuntimeException $e) {
            return $this->redirectWith('/portal/manufacturer/media', 'error', $e->getMessage());
        } catch (\Throwable) {
            return $this->redirectWith('/portal/manufacturer/media', 'error', 'Upload failed. Ensure migration 096 has been applied.');
        }
    }

    public function dealers(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $q = trim((string) $request->query('q', ''));
        $linked = [];
        $candidates = [];
        try {
            $linked = $this->portal->listLinkedDealers((int) $gate['id']);
            $candidates = $this->portal->searchableDealers(current_brand()->databaseId(), $q);
        } catch (\Throwable) {
            // Tables may be absent before migration 096.
        }
        return $this->view('polaris.portal.dealers', [
            'title' => 'Dealer relationships',
            'manufacturer' => $gate,
            'query' => $q,
            'linked' => $linked,
            'candidates' => $candidates,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function linkDealer(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        try {
            $this->portal->linkDealer((int) $gate['id'], (int) $request->input('dealer_id'));
            return $this->redirectWith('/portal/manufacturer/dealers', 'success', 'Dealer linked.');
        } catch (\Throwable) {
            return $this->redirectWith('/portal/manufacturer/dealers', 'error', 'Could not link dealer. Apply migration 096 if needed.');
        }
    }

    public function analytics(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $days = (int) $request->query('days', 30);
        if (!in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }
        $summary = AnalyticsService::manufacturerSummary(
            current_brand()->databaseId(),
            (int) $gate['id'],
            $days
        );
        return $this->view('polaris.portal.analytics', [
            'title' => 'Profile analytics',
            'manufacturer' => $gate,
            'summary' => $summary,
            'days' => $days,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function team(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $team = [];
        try {
            $team = $this->portal->listTeam((int) $gate['id']);
        } catch (\Throwable) {
            $team = [];
        }
        return $this->view('polaris.portal.team', [
            'title' => 'Team permissions',
            'manufacturer' => $gate,
            'team' => $team,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    public function addTeamMember(Request $request): Response
    {
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $userId = (int) $request->input('user_id');
        if ($userId < 1) {
            return $this->redirectWith('/portal/manufacturer/team', 'error', 'User ID is required.');
        }
        $user = Database::selectOne('SELECT id FROM users WHERE id = ? LIMIT 1', [$userId]);
        if ($user === null) {
            return $this->redirectWith('/portal/manufacturer/team', 'error', 'User not found.');
        }
        try {
            $this->portal->addTeamMember((int) $gate['id'], $userId, (string) $request->input('role_label', 'editor'));
            return $this->redirectWith('/portal/manufacturer/team', 'success', 'Team member added.');
        } catch (\Throwable) {
            return $this->redirectWith('/portal/manufacturer/team', 'error', 'Could not add team member. Apply migration 096 if needed.');
        }
    }

    public function dataQuality(Request $request): Response
    {
        unset($request);
        $gate = $this->claimedOrRedirect();
        if ($gate instanceof Response) {
            return $gate;
        }
        $report = $this->dataQuality->reportForManufacturer(
            current_brand()->databaseId(),
            (int) $gate['id']
        );
        return $this->view('polaris.portal.data-quality', [
            'title' => 'Data quality',
            'manufacturer' => $gate,
            'report' => $report,
            'metaRobots' => 'noindex,nofollow',
        ]);
    }

    /** @return array<string,mixed>|Response */
    private function claimedOrRedirect(): array|Response
    {
        if (current_brand()->id() !== 'polaris' || !current_brand()->moduleEnabled('rv_catalogue')) {
            $this->abort(404);
        }
        if (current_user() === null) {
            return $this->redirect('/login?return=' . rawurlencode('/portal/manufacturer'));
        }
        $claimed = $this->claims->claimedManufacturerForUser(current_brand()->databaseId(), (int) current_user()['id']);
        if ($claimed === null) {
            return $this->redirect('/portal/manufacturer/claims');
        }
        return $claimed;
    }
}
