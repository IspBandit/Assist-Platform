<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\Provider;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\FoundingGraphicService;
use App\Services\ProviderClaimService;
use App\Services\SubscriptionService;

/**
 * Admin management of providers: listing, profile editing, approval/verification
 * workflow, service & area management, document verification and internal notes.
 */
final class ProvidersController extends Controller
{
    public function index(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $filters = [
            'status'   => (string) $request->input('status', ''),
            'search'   => trim((string) $request->input('q', '')),
            'town'     => trim((string) $request->input('town', '')),
            'category' => (int) $request->input('category', 0),
            'state'    => (int) $request->input('state', 0),
            'source'   => (string) $request->input('source', ''),
            'verified' => $request->input('verified') ? 1 : 0,
            'featured' => $request->input('featured') ? 1 : 0,
        ];
        $page = max(1, (int) $request->input('page', 1));
        $perPage = 25;

        $result = Provider::adminListing($filters, $perPage, ($page - 1) * $perPage);
        $claimInviteStats = null;
        if (($filters['source'] ?? '') === 'unclaimed') {
            $claimInviteStats = ProviderClaimService::bulkInviteStats($filters);
        }

        return $this->view('admin.providers.index', [
            'title'      => 'Providers',
            'providers'  => $result['rows'],
            'total'      => $result['total'],
            'page'       => $page,
            'perPage'    => $perPage,
            'filters'    => $filters,
            'categories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
            'states'     => Database::select('SELECT id, name FROM states WHERE is_active = 1 ORDER BY name'),
            'claimInviteStats' => $claimInviteStats,
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $provider = $this->findOr404((int) $request->input('id'));
        $id = (int) $provider['id'];
        $foundingPromo = FoundingGraphicService::forProvider($id);

        return $this->view('admin.providers.show', [
            'title'         => $provider['business_name'],
            'provider'      => $provider,
            'services'      => Provider::services($id),
            'areas'         => Provider::areas($id),
            'documents'     => Database::select('SELECT * FROM provider_documents WHERE provider_id = ? ORDER BY created_at DESC', [$id]),
            'licences'      => Database::select('SELECT * FROM provider_licences WHERE provider_id = ? ORDER BY expiry_date', [$id]),
            'notes'         => Database::select(
                'SELECT n.*, u.name AS admin_name FROM provider_internal_notes n '
                . 'LEFT JOIN users u ON u.id = n.admin_id WHERE n.provider_id = ? ORDER BY n.id DESC',
                [$id]
            ),
            'allCategories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
            'allTowns'      => Database::select("SELECT t.id, CONCAT(t.name, ' / ', s.abbreviation) AS name FROM towns t JOIN states s ON s.id=t.state_id WHERE t.is_active=1 ORDER BY t.name,s.abbreviation"),
            'allRegions'    => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'foundingPromo' => $foundingPromo,
            'promoImageUrls' => $foundingPromo !== null ? FoundingGraphicService::imageUrls($foundingPromo) : ['desktop' => null, 'mobile' => null],
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $provider = $id ? $this->findOr404($id) : null;

        return $this->view('admin.providers.form', [
            'title'    => $provider ? 'Edit provider' : 'New provider',
            'provider' => $provider,
            'towns'    => Database::select("SELECT t.id, CONCAT(t.name, ' / ', s.abbreviation) AS name FROM towns t JOIN states s ON s.id=t.state_id WHERE t.is_active=1 ORDER BY t.name,s.abbreviation"),
            'regions'  => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $name = trim((string) $request->input('business_name'));
        if ($name === '') {
            return $this->redirectWith('/admin/providers', 'error', 'Business name is required.');
        }

        $data = [
            'business_name'     => $name,
            'contact_name'      => trim((string) $request->input('contact_name')) ?: null,
            'abn'               => trim((string) $request->input('abn')) ?: null,
            'email'             => trim((string) $request->input('email')) ?: null,
            'phone'             => trim((string) $request->input('phone')) ?: null,
            'public_email'      => trim((string) $request->input('public_email')) ?: null,
            'public_phone'      => trim((string) $request->input('public_phone')) ?: null,
            'website'           => trim((string) $request->input('website')) ?: null,
            'base_town_id'      => (int) $request->input('base_town_id') ?: null,
            'region_id'         => (int) $request->input('region_id') ?: null,
            'service_model'     => in_array($request->input('service_model'), ['mobile', 'workshop', 'both'], true) ? $request->input('service_model') : 'mobile',
            'max_travel_km'     => (int) $request->input('max_travel_km') ?: null,
            'description'       => trim((string) $request->input('description')) ?: null,
            'show_public_phone' => $request->input('show_public_phone') ? 1 : 0,
            'show_public_email' => $request->input('show_public_email') ? 1 : 0,
            'seo_title'         => trim((string) $request->input('seo_title')) ?: null,
            'seo_description'   => trim((string) $request->input('seo_description')) ?: null,
            'updated_at'        => date('Y-m-d H:i:s'),
        ];

        if ($id) {
            Provider::update($id, $data);
            AuditLog::record('provider.updated', 'provider', (string) $id, null, $name);
        } else {
            $data['slug'] = $this->uniqueSlug($name);
            $data['status'] = 'pending';
            $data['created_at'] = date('Y-m-d H:i:s');
            $id = Provider::create($data);
            // Provision billing-side records (dormant during the free launch).
            (new SubscriptionService())->provisionProvider($id, [
                'founding' => (bool) $request->input('is_founding_provider'),
            ]);
            AuditLog::record('provider.created', 'provider', (string) $id, null, $name);
        }

        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Provider saved.');
    }

    public function setStatus(Request $request): Response
    {
        $this->requirePermission('providers.approve');
        $provider = $this->findOr404((int) $request->input('id'));
        $id = (int) $provider['id'];
        $action = (string) $request->input('action');

        $map = [
            'approve'    => 'active',
            'reject'     => 'rejected',
            'suspend'    => 'suspended',
            'reactivate' => 'active',
        ];
        if (!isset($map[$action])) {
            $this->abort(400, 'Unknown action.');
        }
        $newStatus = $map[$action];

        $extra = $action === 'approve' ? ', approved_at = NOW()' : '';
        Database::query("UPDATE providers SET status = ?{$extra}, updated_at = NOW() WHERE id = ?", [$newStatus, $id]);

        $email = $provider['email'] ?: $provider['user_email'];
        if ($email && $action === 'approve') {
            EmailQueue::queueTemplate('provider_approved', $email, $provider['business_name'], [
                'provider_name' => $provider['business_name'],
                'action_url'    => url('provider'),
            ]);
        } elseif ($email && $action === 'reject') {
            EmailQueue::queueTemplate('provider_rejected', $email, $provider['business_name'], [
                'provider_name' => $provider['business_name'],
            ]);
        }

        AuditLog::record('provider.status_' . $action, 'provider', (string) $id, (string) $provider['status'], $newStatus);
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Provider status updated.');
    }

    public function toggleFlag(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $provider = $this->findOr404((int) $request->input('id'));
        $id = (int) $provider['id'];
        $flag = (string) $request->input('flag');
        $columns = ['verified' => 'is_verified', 'insurance' => 'insurance_verified', 'featured' => 'is_featured'];
        if (!isset($columns[$flag])) {
            $this->abort(400);
        }
        $col = $columns[$flag];
        if ($flag === 'verified' || $flag === 'insurance') {
            $this->requirePermission('documents.verify');
        }
        $new = $provider[$col] ? 0 : 1;
        Database::query("UPDATE providers SET {$col} = ?, updated_at = NOW() WHERE id = ?", [$new, $id]);
        if ($flag === 'verified' && $new === 1) {
            FoundingGraphicService::onVerified($id);
        }
        AuditLog::record('provider.flag_' . $flag, 'provider', (string) $id, (string) $provider[$col], (string) $new);
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Updated.');
    }

    public function addNote(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        $body = trim((string) $request->input('body'));
        if ($body !== '') {
            Database::query(
                'INSERT INTO provider_internal_notes (provider_id, admin_id, body, created_at) VALUES (?, ?, ?, NOW())',
                [$id, current_user()['id'] ?? null, $body]
            );
        }
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Note added.');
    }

    public function addService(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        $categoryId = (int) $request->input('category_id');
        if ($categoryId > 0) {
            Database::query(
                'INSERT IGNORE INTO provider_services (provider_id, category_id, created_at) VALUES (?, ?, NOW())',
                [$id, $categoryId]
            );
        }
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Service added.');
    }

    public function removeService(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        Database::query('DELETE FROM provider_services WHERE id = ? AND provider_id = ?', [(int) $request->input('service_id'), $id]);
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Service removed.');
    }

    public function addArea(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        $type = (string) $request->input('area_type');
        if (!in_array($type, ['town', 'region', 'state', 'radius', 'corridor', 'park'], true)) {
            $this->abort(400);
        }
        Database::query(
            'INSERT INTO provider_service_areas (provider_id, area_type, town_id, region_id, state_id, radius_km, label, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                $id, $type,
                $type === 'town' ? ((int) $request->input('town_id') ?: null) : null,
                $type === 'region' ? ((int) $request->input('region_id') ?: null) : null,
                $type === 'state' ? ((int) $request->input('state_id') ?: null) : null,
                $type === 'radius' ? ((int) $request->input('radius_km') ?: null) : null,
                trim((string) $request->input('label')) ?: null,
            ]
        );
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Service area added.');
    }

    public function removeArea(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        Database::query('DELETE FROM provider_service_areas WHERE id = ? AND provider_id = ?', [(int) $request->input('area_id'), $id]);
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Service area removed.');
    }

    public function downloadDocument(Request $request): Response
    {
        $this->requirePermission('documents.verify');
        $doc = Database::selectOne('SELECT * FROM provider_documents WHERE id = ?', [(int) $request->input('document_id')]);
        if ($doc === null) {
            $this->abort(404);
        }
        return \App\Services\FileStorage::serve(
            'provider_documents',
            (string) $doc['stored_name'],
            (string) $doc['original_name'],
            (string) $doc['mime_type']
        );
    }

    public function verifyDocument(Request $request): Response
    {
        $this->requirePermission('documents.verify');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        $docId = (int) $request->input('document_id');
        $status = (string) $request->input('verification_status');
        if (!in_array($status, ['pending', 'verified', 'rejected'], true)) {
            $this->abort(400);
        }
        Database::query(
            'UPDATE provider_documents SET verification_status = ?, verification_notes = ?, verified_by = ?, verified_at = NOW() '
            . 'WHERE id = ? AND provider_id = ?',
            [$status, trim((string) $request->input('notes')) ?: null, current_user()['id'] ?? null, $docId, $id]
        );
        AuditLog::record('provider.document_' . $status, 'provider_document', (string) $docId);
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Document updated.');
    }

    public function verifyLicence(Request $request): Response
    {
        $this->requirePermission('documents.verify');
        $id = (int) $request->input('id');
        $this->findOr404($id);
        $licId = (int) $request->input('licence_id');
        $status = (string) $request->input('verification_status');
        if (!in_array($status, ['pending', 'verified', 'rejected', 'expired'], true)) {
            $this->abort(400);
        }
        Database::query(
            'UPDATE provider_licences SET verification_status = ?, verification_notes = ?, updated_at = NOW() WHERE id = ? AND provider_id = ?',
            [$status, trim((string) $request->input('notes')) ?: null, $licId, $id]
        );
        AuditLog::record('provider.licence_' . $status, 'provider_licence', (string) $licId);
        return $this->redirectWith('/admin/providers/show?id=' . $id, 'success', 'Licence updated.');
    }

    // --- helpers ------------------------------------------------------------

    public function sendClaimInvite(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        $provider = $this->findOr404((int) $request->input('id'));
        if (empty($provider['is_unclaimed'])) {
            return $this->redirectWith('/admin/providers/show?id=' . (int) $provider['id'], 'error', 'Only unclaimed listings can receive a claim invite.');
        }
        try {
            $url = ProviderClaimService::sendClaimInvite((int) $provider['id'], (string) $request->input('email', ''), auth()->id());
        } catch (\Throwable $e) {
            return $this->redirectWith('/admin/providers/show?id=' . (int) $provider['id'], 'error', $e->getMessage());
        }
        AuditLog::record('provider.claim_invite_sent', 'provider', (string) $provider['id']);
        return $this->redirectWith('/admin/providers/show?id=' . (int) $provider['id'], 'success', 'Claim invite queued. Link (for testing): ' . $url);
    }

    public function bulkClaimInvites(Request $request): Response
    {
        $this->requirePermission('providers.manage');

        $filters = [
            'status'   => (string) $request->input('status', ''),
            'search'   => trim((string) $request->input('q', '')),
            'town'     => trim((string) $request->input('town', '')),
            'category' => (int) $request->input('category', 0),
            'state'    => (int) $request->input('state', 0),
            'source'   => 'unclaimed',
            'verified' => $request->input('verified') ? 1 : 0,
            'featured' => $request->input('featured') ? 1 : 0,
        ];
        $offset = max(0, (int) $request->input('offset', 0));

        $result = ProviderClaimService::runBulkInvites($filters, $offset, 25, auth()->id());
        AuditLog::record('provider.bulk_claim_invites', 'provider', null, null, json_encode([
            'offset' => $offset,
            'sent'   => $result['sent'],
            'failed' => $result['failed'],
        ]));

        $qs = http_build_query(array_filter([
            'source'   => 'unclaimed',
            'status'   => $filters['status'] ?: null,
            'q'        => $filters['search'] ?: null,
            'town'     => $filters['town'] ?: null,
            'category' => $filters['category'] ?: null,
            'state'    => $filters['state'] ?: null,
            'verified' => $filters['verified'] ?: null,
            'featured' => $filters['featured'] ?: null,
            'invite_offset' => $result['done'] ? null : $result['next_offset'],
        ], static fn ($v) => $v !== null && $v !== '' && $v !== 0));

        $msg = 'Queued ' . $result['sent'] . ' claim invite(s)';
        if ($result['failed'] > 0) {
            $msg .= '; ' . $result['failed'] . ' failed';
            if ($result['errors'] !== []) {
                $msg .= ' (' . implode('; ', $result['errors']) . ')';
            }
        }
        if (!$result['done']) {
            $msg .= '. Click Continue bulk invites to send the next batch (' . $result['next_offset'] . ' processed so far).';
        } else {
            $msg .= '. Bulk send complete — process the email queue to deliver them.';
        }

        return $this->redirectWith('/admin/providers' . ($qs !== '' ? ('?' . $qs) : ''), $result['failed'] > 0 && $result['sent'] === 0 ? 'error' : 'success', $msg);
    }

    public function duplicates(Request $request): Response
    {
        $this->requirePermission('providers.manage');
        return $this->view('admin.providers.duplicates', [
            'title'      => 'Possible duplicate providers',
            'duplicates' => ProviderClaimService::duplicateSuspects(150),
        ]);
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $provider = Provider::adminFind($id);
        if ($provider === null) {
            $this->abort(404, 'Provider not found.');
        }
        return $provider;
    }

    private function uniqueSlug(string $source): string
    {
        $base = str_slug($source) ?: 'provider';
        $slug = $base;
        $n = 1;
        while ((int) Database::scalar('SELECT COUNT(*) FROM providers WHERE slug = ?', [$slug]) > 0) {
            $slug = $base . '-' . (++$n);
        }
        return $slug;
    }
}
