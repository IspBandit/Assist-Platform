<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuditLog;
use App\Services\FileStorage;
use RuntimeException;

/**
 * Provider self-service: business profile, services, service areas, verification
 * documents (secure upload/download), licences and availability. Status,
 * verification and featured flags remain admin-controlled.
 */
final class ProfileController extends Controller
{
    private const DOC_TYPES = ['insurance', 'licence', 'registration', 'certification', 'other'];

    // ---- Business profile --------------------------------------------------

    public function profile(Request $request): Response
    {
        $provider = $this->requireProvider();
        return $this->view('provider.profile', [
            'title'    => 'Business profile',
            'provider' => $provider,
            'towns'    => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'regions'  => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'errors'   => Session::errors(),
        ]);
    }

    public function saveProfile(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];

        $name = trim((string) $request->input('business_name'));
        if ($name === '') {
            return $this->redirectWith('/provider/profile', 'error', 'Business name is required.');
        }

        Database::query(
            'UPDATE providers SET business_name = ?, contact_name = ?, abn = ?, phone = ?, public_phone = ?, '
            . 'public_email = ?, website = ?, base_town_id = ?, region_id = ?, service_model = ?, max_travel_km = ?, '
            . 'description = ?, show_public_phone = ?, show_public_email = ?, updated_at = NOW() WHERE id = ?',
            [
                $name,
                trim((string) $request->input('contact_name')) ?: null,
                trim((string) $request->input('abn')) ?: null,
                trim((string) $request->input('phone')) ?: null,
                trim((string) $request->input('public_phone')) ?: null,
                trim((string) $request->input('public_email')) ?: null,
                trim((string) $request->input('website')) ?: null,
                (int) $request->input('base_town_id') ?: null,
                (int) $request->input('region_id') ?: null,
                in_array($request->input('service_model'), ['mobile', 'workshop', 'both'], true) ? $request->input('service_model') : 'mobile',
                (int) $request->input('max_travel_km') ?: null,
                trim((string) $request->input('description')) ?: null,
                $request->input('show_public_phone') ? 1 : 0,
                $request->input('show_public_email') ? 1 : 0,
                $id,
            ]
        );

        AuditLog::record('provider.self_updated', 'provider', (string) $id, null, $name);
        return $this->redirectWith('/provider/profile', 'success', 'Profile updated.');
    }

    // ---- Services ----------------------------------------------------------

    public function services(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        return $this->view('provider.services', [
            'title'      => 'Services',
            'provider'   => $provider,
            'services'   => Database::select(
                'SELECT ps.id, c.name, c.slug FROM provider_services ps JOIN service_categories c ON c.id = ps.category_id '
                . 'WHERE ps.provider_id = ? ORDER BY c.name',
                [$id]
            ),
            'categories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function addService(Request $request): Response
    {
        $provider = $this->requireProvider();
        $categoryId = (int) $request->input('category_id');
        if ($categoryId > 0) {
            Database::query(
                'INSERT IGNORE INTO provider_services (provider_id, category_id, created_at) VALUES (?, ?, NOW())',
                [(int) $provider['id'], $categoryId]
            );
        }
        return $this->redirectWith('/provider/services', 'success', 'Service added.');
    }

    public function removeService(Request $request): Response
    {
        $provider = $this->requireProvider();
        Database::query(
            'DELETE FROM provider_services WHERE id = ? AND provider_id = ?',
            [(int) $request->input('service_id'), (int) $provider['id']]
        );
        return $this->redirectWith('/provider/services', 'success', 'Service removed.');
    }

    // ---- Service areas -----------------------------------------------------

    public function areas(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        return $this->view('provider.areas', [
            'title'    => 'Service areas',
            'provider' => $provider,
            'areas'    => Database::select(
                'SELECT a.*, t.name AS town_name, r.name AS region_name, s.name AS state_name FROM provider_service_areas a '
                . 'LEFT JOIN towns t ON t.id = a.town_id LEFT JOIN regions r ON r.id = a.region_id '
                . 'LEFT JOIN states s ON s.id = a.state_id '
                . 'WHERE a.provider_id = ? ORDER BY a.area_type',
                [$id]
            ),
            'towns'    => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name LIMIT 500'),
            'regions'  => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'states'   => Database::select('SELECT id, name, abbreviation FROM states WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function addArea(Request $request): Response
    {
        $provider = $this->requireProvider();
        $type = (string) $request->input('area_type');
        if (!in_array($type, ['town', 'region', 'state', 'radius'], true)) {
            $this->abort(400);
        }
        Database::query(
            'INSERT INTO provider_service_areas (provider_id, area_type, town_id, region_id, state_id, radius_km, label, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [
                (int) $provider['id'], $type,
                $type === 'town' ? ((int) $request->input('town_id') ?: null) : null,
                $type === 'region' ? ((int) $request->input('region_id') ?: null) : null,
                $type === 'state' ? ((int) $request->input('state_id') ?: null) : null,
                $type === 'radius' ? ((int) $request->input('radius_km') ?: null) : null,
                trim((string) $request->input('label')) ?: null,
            ]
        );
        return $this->redirectWith('/provider/areas', 'success', 'Service area added.');
    }

    public function removeArea(Request $request): Response
    {
        $provider = $this->requireProvider();
        Database::query(
            'DELETE FROM provider_service_areas WHERE id = ? AND provider_id = ?',
            [(int) $request->input('area_id'), (int) $provider['id']]
        );
        return $this->redirectWith('/provider/areas', 'success', 'Service area removed.');
    }

    // ---- Verification documents -------------------------------------------

    public function documents(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        return $this->view('provider.documents', [
            'title'     => 'Verification documents',
            'provider'  => $provider,
            'documents' => Database::select('SELECT * FROM provider_documents WHERE provider_id = ? ORDER BY created_at DESC', [$id]),
            'docTypes'  => self::DOC_TYPES,
        ]);
    }

    public function uploadDocument(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        $docType = in_array($request->input('doc_type'), self::DOC_TYPES, true) ? (string) $request->input('doc_type') : 'other';

        $file = $request->file('document');
        if ($file === null) {
            return $this->redirectWith('/provider/documents', 'error', 'Please choose a file to upload.');
        }

        try {
            $maxBytes = ((int) config('uploads.max_document_mb', 10)) * 1024 * 1024;
            $stored = FileStorage::storeUpload(
                $file,
                'provider_documents',
                (array) config('uploads.allowed_document_mimes', []),
                $maxBytes
            );
        } catch (RuntimeException $e) {
            return $this->redirectWith('/provider/documents', 'error', $e->getMessage());
        }

        Database::query(
            'INSERT INTO provider_documents (provider_id, doc_type, original_name, stored_name, mime_type, file_size, verification_status, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
            [$id, $docType, $stored['original_name'], $stored['stored_name'], $stored['mime_type'], $stored['file_size'], 'pending']
        );
        AuditLog::record('provider.document_uploaded', 'provider', (string) $id, null, $docType);
        return $this->redirectWith('/provider/documents', 'success', 'Document uploaded for review.');
    }

    public function deleteDocument(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        $doc = Database::selectOne('SELECT * FROM provider_documents WHERE id = ? AND provider_id = ?', [(int) $request->input('document_id'), $id]);
        if ($doc === null) {
            $this->abort(404);
        }
        if ($doc['verification_status'] === 'verified') {
            return $this->redirectWith('/provider/documents', 'error', 'Verified documents cannot be removed. Contact support if it needs replacing.');
        }
        FileStorage::delete('provider_documents', (string) $doc['stored_name']);
        Database::query('DELETE FROM provider_documents WHERE id = ? AND provider_id = ?', [(int) $doc['id'], $id]);
        return $this->redirectWith('/provider/documents', 'success', 'Document removed.');
    }

    public function downloadDocument(Request $request): Response
    {
        $provider = $this->requireProvider();
        $doc = Database::selectOne(
            'SELECT * FROM provider_documents WHERE id = ? AND provider_id = ?',
            [(int) $request->input('id'), (int) $provider['id']]
        );
        if ($doc === null) {
            $this->abort(404);
        }
        return FileStorage::serve('provider_documents', (string) $doc['stored_name'], (string) $doc['original_name'], (string) $doc['mime_type']);
    }

    // ---- Licences ----------------------------------------------------------

    public function licences(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        return $this->view('provider.licences', [
            'title'    => 'Licences & credentials',
            'provider' => $provider,
            'licences' => Database::select('SELECT * FROM provider_licences WHERE provider_id = ? ORDER BY expiry_date', [$id]),
        ]);
    }

    public function saveLicence(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        $type = trim((string) $request->input('licence_type'));
        if ($type === '') {
            return $this->redirectWith('/provider/licences', 'error', 'A licence type is required.');
        }
        Database::query(
            'INSERT INTO provider_licences (provider_id, licence_type, licence_number, issuing_authority, issue_date, expiry_date, display_publicly, verification_status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $id, $type,
                trim((string) $request->input('licence_number')) ?: null,
                trim((string) $request->input('issuing_authority')) ?: null,
                $request->input('issue_date') ?: null,
                $request->input('expiry_date') ?: null,
                $request->input('display_publicly') ? 1 : 0,
                'pending',
            ]
        );
        AuditLog::record('provider.licence_added', 'provider', (string) $id, null, $type);
        return $this->redirectWith('/provider/licences', 'success', 'Licence added for review.');
    }

    public function deleteLicence(Request $request): Response
    {
        $provider = $this->requireProvider();
        Database::query(
            'DELETE FROM provider_licences WHERE id = ? AND provider_id = ?',
            [(int) $request->input('licence_id'), (int) $provider['id']]
        );
        return $this->redirectWith('/provider/licences', 'success', 'Licence removed.');
    }

    // ---- Availability ------------------------------------------------------

    public function availability(Request $request): Response
    {
        $provider = $this->requireProvider();
        $id = (int) $provider['id'];
        return $this->view('provider.availability', [
            'title'     => 'Availability',
            'provider'  => $provider,
            'windows'   => Database::select('SELECT * FROM provider_availability WHERE provider_id = ? ORDER BY start_date DESC', [$id]),
        ]);
    }

    public function addAvailability(Request $request): Response
    {
        $provider = $this->requireProvider();
        $start = (string) $request->input('start_date');
        if ($start === '') {
            return $this->redirectWith('/provider/availability', 'error', 'A start date is required.');
        }
        Database::query(
            'INSERT INTO provider_availability (provider_id, start_date, end_date, is_available, notes, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
            [
                (int) $provider['id'],
                $start,
                $request->input('end_date') ?: null,
                $request->input('is_available') === '0' ? 0 : 1,
                trim((string) $request->input('notes')) ?: null,
            ]
        );
        return $this->redirectWith('/provider/availability', 'success', 'Availability saved.');
    }

    public function removeAvailability(Request $request): Response
    {
        $provider = $this->requireProvider();
        Database::query(
            'DELETE FROM provider_availability WHERE id = ? AND provider_id = ?',
            [(int) $request->input('window_id'), (int) $provider['id']]
        );
        return $this->redirectWith('/provider/availability', 'success', 'Availability removed.');
    }

    // ---- helpers -----------------------------------------------------------

    /** @return array<string,mixed> */
    private function requireProvider(): array
    {
        $user = current_user();
        $provider = $user ? Database::selectOne('SELECT * FROM providers WHERE user_id = ? AND deleted_at IS NULL', [(int) $user['id']]) : null;
        if ($provider === null) {
            $this->abort(404, 'No provider profile is linked to your account.');
        }
        return $provider;
    }
}
