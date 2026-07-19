<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\CaravanPark;
use App\Models\ServiceRequest;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\FileStorage;
use App\Services\QrCode;
use App\Services\RequestWorkflow;
use RuntimeException;

/**
 * Caravan park partner self-service portal: dashboard, public profile, documents,
 * registering guest requests, nearby runs, requesting a service day, and the
 * park-specific QR code and printable materials.
 */
final class ParkController extends Controller
{
    public function dashboard(Request $request): Response
    {
        $park = $this->requirePark();
        $id = (int) $park['id'];

        $checklist = [
            'Add a description'      => (bool) $park['description'],
            'Set your town'          => (bool) $park['town_id'],
            'Add contact phone'      => (bool) $park['phone'],
            'Enable your public page' => (bool) $park['public_page_enabled'],
        ];
        $complete = count(array_filter($checklist));

        return $this->view('park.dashboard', [
            'title'        => 'Caravan park dashboard',
            'park'         => $park,
            'checklist'    => $checklist,
            'complete'     => $complete,
            'totalChecks'  => count($checklist),
            'recentRequests' => Database::select(
                'SELECT id, reference, title, status, created_at FROM service_requests '
                . 'WHERE park_id = ? AND deleted_at IS NULL ORDER BY created_at DESC LIMIT 5',
                [$id]
            ),
            'serviceDays'  => array_slice(CaravanPark::serviceDayRequests($id), 0, 5),
        ]);
    }

    // ---- Public profile ----------------------------------------------------

    public function profile(Request $request): Response
    {
        $park = $this->requirePark();
        return $this->view('park.profile', [
            'title'   => 'Park profile',
            'park'    => $park,
            'towns'   => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'regions' => Database::select('SELECT id, name FROM regions WHERE is_active = 1 ORDER BY name'),
            'errors'  => Session::errors(),
        ]);
    }

    public function saveProfile(Request $request): Response
    {
        $park = $this->requirePark();
        $id = (int) $park['id'];

        $name = trim((string) $request->input('name'));
        if ($name === '') {
            return $this->redirectWith('/park/profile', 'error', 'Park name is required.');
        }

        $townId = (int) $request->input('town_id') ?: null;
        $town = $townId ? Database::selectOne('SELECT region_id, state_id FROM towns WHERE id = ?', [$townId]) : null;

        Database::query(
            'UPDATE caravan_parks SET name = ?, address = ?, town_id = ?, region_id = ?, state_id = ?, phone = ?, '
            . 'email = ?, website = ?, facebook_url = ?, description = ?, number_of_sites = ?, guest_request_contact = ?, '
            . 'public_page_enabled = ?, seo_title = ?, seo_description = ?, updated_at = NOW() WHERE id = ?',
            [
                $name,
                trim((string) $request->input('address')) ?: null,
                $townId,
                $town['region_id'] ?? null,
                $town['state_id'] ?? null,
                trim((string) $request->input('phone')) ?: null,
                trim((string) $request->input('email')) ?: null,
                trim((string) $request->input('website')) ?: null,
                trim((string) $request->input('facebook_url')) ?: null,
                trim((string) $request->input('description')) ?: null,
                (int) $request->input('number_of_sites') ?: null,
                trim((string) $request->input('guest_request_contact')) ?: null,
                $request->input('public_page_enabled') ? 1 : 0,
                trim((string) $request->input('seo_title')) ?: null,
                trim((string) $request->input('seo_description')) ?: null,
                $id,
            ]
        );

        $this->handleLogoUpload($request, $park);

        AuditLog::record('park.profile_updated', 'caravan_park', (string) $id);
        return $this->redirectWith('/park/profile', 'success', 'Park profile saved.');
    }

    private function handleLogoUpload(Request $request, array $park): void
    {
        $file = $request->file('logo');
        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }
        try {
            $meta = FileStorage::storeUpload($file, 'park_logos', (array) config('uploads.allowed_image_mimes'), (int) config('uploads.max_image_mb', 8) * 1024 * 1024);
        } catch (RuntimeException $e) {
            Session::flash('error', 'Logo not saved: ' . $e->getMessage());
            return;
        }
        if (!empty($park['logo_path'])) {
            FileStorage::delete('park_logos', (string) $park['logo_path']);
        }
        Database::query('UPDATE caravan_parks SET logo_path = ?, updated_at = NOW() WHERE id = ?', [$meta['stored_name'], (int) $park['id']]);
    }

    // ---- Documents ---------------------------------------------------------

    public function documents(Request $request): Response
    {
        $park = $this->requirePark();
        return $this->view('park.documents', [
            'title'     => 'Park documents',
            'park'      => $park,
            'documents' => CaravanPark::documents((int) $park['id']),
        ]);
    }

    public function uploadDocument(Request $request): Response
    {
        $park = $this->requirePark();
        $id = (int) $park['id'];
        $file = $request->file('document');
        $docType = trim((string) $request->input('doc_type')) ?: 'other';

        try {
            $meta = FileStorage::storeUpload($file ?? [], 'park_documents', (array) config('uploads.allowed_document_mimes'), (int) config('uploads.max_document_mb', 10) * 1024 * 1024);
        } catch (RuntimeException $e) {
            return $this->redirectWith('/park/documents', 'error', $e->getMessage());
        }

        Database::query(
            'INSERT INTO caravan_park_documents (park_id, doc_type, stored_name, original_name, mime_type, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, NOW())',
            [$id, $docType, $meta['stored_name'], $meta['original_name'], $meta['mime_type']]
        );
        AuditLog::record('park.document_uploaded', 'caravan_park', (string) $id);
        return $this->redirectWith('/park/documents', 'success', 'Document uploaded.');
    }

    public function deleteDocument(Request $request): Response
    {
        $park = $this->requirePark();
        $doc = $this->findOwnDocument((int) $request->input('document_id'), (int) $park['id']);
        FileStorage::delete('park_documents', (string) $doc['stored_name']);
        Database::query('DELETE FROM caravan_park_documents WHERE id = ?', [(int) $doc['id']]);
        return $this->redirectWith('/park/documents', 'success', 'Document removed.');
    }

    public function downloadDocument(Request $request): Response
    {
        $park = $this->requirePark();
        $doc = $this->findOwnDocument((int) $request->input('document_id'), (int) $park['id']);
        return FileStorage::serve('park_documents', (string) $doc['stored_name'], (string) ($doc['original_name'] ?? 'document'), (string) $doc['mime_type'], false);
    }

    // ---- Register a guest request ------------------------------------------

    public function registerRequest(Request $request): Response
    {
        $park = $this->requirePark();
        return $this->view('park.register-request', [
            'title'      => 'Register a guest request',
            'park'       => $park,
            'towns'      => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'categories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
            'errors'     => Session::errors(),
        ]);
    }

    public function storeRequest(Request $request): Response
    {
        $park = $this->requirePark();
        $id = (int) $park['id'];

        $name = trim((string) $request->input('contact_name'));
        $email = strtolower(trim((string) $request->input('contact_email')));
        $title = trim((string) $request->input('title'));
        $townId = (int) $request->input('town_id') ?: ($park['town_id'] ? (int) $park['town_id'] : null);
        $categoryId = (int) $request->input('primary_category_id') ?: null;

        $errors = [];
        if ($name === '') {
            $errors['contact_name'] = 'Guest name is required.';
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['contact_email'] = 'A valid guest email is required.';
        }
        if ($title === '') {
            $errors['title'] = 'A short summary is required.';
        }
        if ($townId === null) {
            $errors['town_id'] = 'A town is required.';
        }
        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($request->all());
            return $this->redirect('park/register-request');
        }

        $town = Database::selectOne('SELECT id, region_id, state_id FROM towns WHERE id = ?', [$townId]);
        $reference = ServiceRequest::generateReference();

        $requestId = ServiceRequest::create([
            'reference'           => $reference,
            'park_id'             => $id,
            'contact_name'        => $name,
            'contact_email'       => $email,
            'contact_phone'       => trim((string) $request->input('contact_phone')) ?: null,
            'town_id'             => $townId,
            'region_id'           => $town['region_id'] ?? null,
            'state_id'            => $town['state_id'] ?? null,
            'location_label'      => (string) $park['name'],
            'primary_category_id' => $categoryId,
            'title'               => $title,
            'description'         => trim((string) $request->input('description')) ?: null,
            'urgency'             => in_array($request->input('urgency'), ['low', 'medium', 'high', 'urgent'], true) ? $request->input('urgency') : 'medium',
            'consent_terms'       => 1,
            'consent_privacy'     => 1,
            'consent_share'       => 1,
            'status'              => 'pending_moderation',
            'verified_at'         => date('Y-m-d H:i:s'),
            'source'              => 'park',
            'ip_address'          => $request->ip(),
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        if ($categoryId !== null) {
            Database::query(
                'INSERT IGNORE INTO service_request_categories (request_id, category_id, is_primary) VALUES (?, ?, 1)',
                [$requestId, $categoryId]
            );
        }

        RequestWorkflow::recordHistory($requestId, null, 'pending_moderation', current_user()['id'] ?? null, 'Registered by ' . $park['name']);
        AuditLog::record('request.created_by_park', 'service_request', (string) $requestId, null, $reference);

        EmailQueue::queueTemplate('new_request_received', $email, $name, [
            'customer_name'     => $name,
            'request_reference' => $reference,
            'town_name'         => (string) $park['name'],
        ]);

        return $this->redirectWith('/park', 'success', 'Guest request ' . $reference . ' submitted for review.');
    }

    // ---- Nearby runs -------------------------------------------------------

    public function runs(Request $request): Response
    {
        $park = $this->requirePark();
        return $this->view('park.runs', [
            'title' => 'Service runs nearby',
            'park'  => $park,
            'runs'  => CaravanPark::nearbyRuns($park['town_id'] ? (int) $park['town_id'] : null, $park['region_id'] ? (int) $park['region_id'] : null),
        ]);
    }

    // ---- Request a service day ---------------------------------------------

    public function serviceDay(Request $request): Response
    {
        $park = $this->requirePark();
        return $this->view('park.service-day', [
            'title'      => 'Request a service day',
            'park'       => $park,
            'requests'   => CaravanPark::serviceDayRequests((int) $park['id']),
            'categories' => Database::select('SELECT id, name FROM service_categories WHERE is_active = 1 ORDER BY name'),
        ]);
    }

    public function storeServiceDay(Request $request): Response
    {
        $park = $this->requirePark();
        $id = (int) $park['id'];

        Database::query(
            'INSERT INTO caravan_park_service_day_requests (park_id, requested_by, preferred_dates, category_id, notes, status, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $id,
                current_user()['id'] ?? null,
                trim((string) $request->input('preferred_dates')) ?: null,
                (int) $request->input('category_id') ?: null,
                trim((string) $request->input('notes')) ?: null,
                'open',
            ]
        );
        AuditLog::record('park.service_day_requested', 'caravan_park', (string) $id);
        return $this->redirectWith('/park/service-day', 'success', 'Service-day request sent to the VanAssist team.');
    }

    // ---- QR code & printable materials -------------------------------------

    public function materials(Request $request): Response
    {
        $park = $this->requirePark();
        $requestUrl = url('request-assistance?park=' . $park['slug']);

        return $this->view('park.materials', [
            'title'      => 'QR code & materials',
            'park'       => $park,
            'requestUrl' => $requestUrl,
            'qrDataUri'  => QrCode::svgDataUri($requestUrl, 4, 8),
        ]);
    }

    // ---- Helpers -----------------------------------------------------------

    /** @return array<string,mixed> */
    private function requirePark(): array
    {
        $user = current_user();
        $park = $user ? CaravanPark::forUser((int) $user['id']) : null;
        if ($park === null) {
            $this->abort(404, 'No caravan park is linked to your account.');
        }
        return $park;
    }

    /** @return array<string,mixed> */
    private function findOwnDocument(int $documentId, int $parkId): array
    {
        $doc = Database::selectOne('SELECT * FROM caravan_park_documents WHERE id = ? AND park_id = ?', [$documentId, $parkId]);
        if ($doc === null) {
            $this->abort(404, 'Document not found.');
        }
        return $doc;
    }
}
