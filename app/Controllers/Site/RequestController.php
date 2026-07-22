<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\ServiceRequest;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\ImageProcessor;
use App\Services\RequestWorkflow;
use App\Validation\Validator;
use RuntimeException;

/**
 * Public multi-step "request assistance" flow: a single sectioned form
 * (location -> vehicle -> service -> fault -> images -> contact/consent),
 * double opt-in email verification for guests, and a confirmation page.
 */
final class RequestController extends Controller
{
    private const VEHICLE_TYPES = ['caravan', 'camper_trailer', 'motorhome', 'campervan', 'fifth_wheeler', 'other'];
    private const URGENCIES = ['low', 'medium', 'high', 'urgent'];

    public function form(Request $request): Response
    {
        $park = $this->resolvePark($request->input('park'));
        $prefillTownId = $this->resolvePrefillTownId($request);
        if ($prefillTownId === null && $park !== null && !empty($park['town_id'])) {
            $prefillTownId = (int) $park['town_id'];
        }
        $prefillCategoryId = $this->resolvePrefillCategoryId($request);
        $prefillUrgency = $this->mapTimeframeToUrgency((string) $request->input('timeframe', ''));

        return $this->view('public.request-form', [
            'title'           => 'Request caravan assistance — VanAssist',
            'metaDescription' => 'Tell us where you are, your vehicle and the problem. We coordinate suitable providers and service runs across regional Australia.',
            'canonical'       => url('request-assistance'),
            'categories'      => Database::select('SELECT id, name, slug FROM service_categories WHERE is_active = 1 ORDER BY name'),
            'vehicleTypes'    => self::VEHICLE_TYPES,
            'urgencies'       => self::URGENCIES,
            'prefillTownId'   => $prefillTownId,
            'prefillTownLabel'=> $this->prefillTownLabel($prefillTownId, $request),
            'prefillCategoryId' => $prefillCategoryId,
            'prefillUrgency'  => $prefillUrgency,
            'park'            => $park,
            'errors'          => Session::errors(),
        ]);
    }

    private function resolvePrefillTownId(Request $request): ?int
    {
        if ($request->filled('town')) {
            $row = Database::selectOne(
                'SELECT id FROM towns WHERE slug = ? OR id = ? LIMIT 1',
                [(string) $request->input('town'), (int) $request->input('town')]
            );
            if ($row) {
                return (int) $row['id'];
            }
        }
        $location = trim((string) $request->input('location', ''));
        if ($location !== '') {
            $matches = \App\Models\Town::searchActive($location, 1);
            if ($matches !== []) {
                return (int) $matches[0]['id'];
            }
        }
        return null;
    }

    private function prefillTownLabel(?int $townId, Request $request): string
    {
        if ($townId !== null) {
            $row = Database::selectOne(
                'SELECT t.name, s.abbreviation AS state_abbr FROM towns t LEFT JOIN states s ON s.id = t.state_id WHERE t.id = ?',
                [$townId]
            );
            if ($row) {
                return (string) $row['name'] . (!empty($row['state_abbr']) ? ' / ' . $row['state_abbr'] : '');
            }
        }
        return trim((string) $request->input('location', ''));
    }

    private function resolvePrefillCategoryId(Request $request): ?int
    {
        $slug = trim((string) $request->input('category', ''));
        if ($slug === '') {
            $id = (int) $request->input('primary_category_id', 0);
            return $id > 0 ? $id : null;
        }
        $cat = \App\Models\ServiceCategory::findActiveBySlug($slug);
        return $cat ? (int) $cat['id'] : null;
    }

    private function mapTimeframeToUrgency(string $timeframe): string
    {
        return match ($timeframe) {
            '2weeks' => 'high',
            'month'   => 'medium',
            default   => 'medium',
        };
    }

    /** @return array<string,mixed>|null an active park referenced by slug */
    private function resolvePark(mixed $slug): ?array
    {
        $slug = is_string($slug) ? trim($slug) : '';
        if ($slug === '') {
            return null;
        }
        return Database::selectOne(
            "SELECT id, name, slug, town_id FROM caravan_parks WHERE slug = ? AND status = 'active' AND deleted_at IS NULL",
            [$slug]
        );
    }

    public function submit(Request $request): Response
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return $this->redirect('request-assistance'); // honeypot
        }

        $data = $request->all();
        $validator = Validator::make($data, [
            'contact_name'        => 'required|max:150',
            'contact_email'       => 'required|email|max:190',
            'title'               => 'required|max:190',
            'town_id'             => 'required|numeric',
            'primary_category_id' => 'required|numeric',
            'consent_terms'       => 'accepted',
            'consent_privacy'     => 'accepted',
        ], [
            'town_id'             => 'Location',
            'primary_category_id' => 'Service type',
            'consent_terms'       => 'Terms',
            'consent_privacy'     => 'Privacy policy',
        ]);

        $errors = $validator->fails() ? $validator->errors() : [];
        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($data);
            return $this->redirect('request-assistance');
        }

        $town = Database::selectOne('SELECT id, region_id, state_id, primary_postcode FROM towns WHERE id = ?', [(int) $request->input('town_id')]);
        $park = $this->resolvePark($request->input('park'));

        $auth = Auth::instance();
        $customerId = null;
        $isVerified = false;
        if ($auth->check()) {
            $user = current_user();
            $customer = Database::selectOne('SELECT id FROM customers WHERE user_id = ?', [(int) $user['id']]);
            $customerId = $customer ? (int) $customer['id'] : null;
            $isVerified = !empty($user['email_verified_at']);
        }

        $reference = ServiceRequest::generateReference();
        $verifyToken = $isVerified ? null : bin2hex(random_bytes(32));
        $status = $isVerified ? 'pending_moderation' : 'awaiting_verification';

        $requestId = ServiceRequest::create([
            'reference'           => $reference,
            'customer_id'         => $customerId,
            'park_id'             => $park ? (int) $park['id'] : null,
            'contact_name'        => trim((string) $request->input('contact_name')),
            'contact_email'       => strtolower(trim((string) $request->input('contact_email'))),
            'contact_phone'       => trim((string) $request->input('contact_phone')) ?: null,
            'preferred_contact'   => in_array($request->input('preferred_contact'), ['email', 'phone', 'either'], true) ? $request->input('preferred_contact') : 'either',
            'town_id'             => $town ? (int) $town['id'] : null,
            'region_id'           => $town ? ($town['region_id'] ?? null) : null,
            'state_id'            => $town ? ($town['state_id'] ?? null) : null,
            'postcode'            => trim((string) $request->input('postcode')) ?: ($town['primary_postcode'] ?? null),
            'location_label'      => trim((string) $request->input('location_label')) ?: null,
            'max_distance_km'     => (int) $request->input('max_distance_km') ?: null,
            'mobile_preferred'    => $request->input('mobile_preferred') ? 1 : 0,
            'workshop_acceptable' => $request->input('workshop_acceptable') ? 1 : 0,
            'vehicle_type'        => in_array($request->input('vehicle_type'), self::VEHICLE_TYPES, true) ? $request->input('vehicle_type') : null,
            'vehicle_make'        => trim((string) $request->input('vehicle_make')) ?: null,
            'vehicle_model'       => trim((string) $request->input('vehicle_model')) ?: null,
            'vehicle_year'        => (int) $request->input('vehicle_year') ?: null,
            'vehicle_length_m'    => $request->input('vehicle_length_m') !== '' ? (float) $request->input('vehicle_length_m') : null,
            'is_usable'           => $request->has('is_usable') ? ($request->input('is_usable') ? 1 : 0) : null,
            'primary_category_id' => (int) $request->input('primary_category_id'),
            'title'               => trim((string) $request->input('title')),
            'description'         => trim((string) $request->input('description')) ?: null,
            'issue_started'       => trim((string) $request->input('issue_started')) ?: null,
            'error_code'          => trim((string) $request->input('error_code')) ?: null,
            'appliance_brand'     => trim((string) $request->input('appliance_brand')) ?: null,
            'appliance_model'     => trim((string) $request->input('appliance_model')) ?: null,
            'safety_concern'      => $request->input('safety_concern') ? 1 : 0,
            'urgency'             => in_array($request->input('urgency'), self::URGENCIES, true) ? $request->input('urgency') : 'medium',
            'travel_deadline'     => $request->input('travel_deadline') ?: null,
            'flexible_dates'      => $request->input('flexible_dates') ? 1 : 0,
            'willing_group_day'   => $request->input('willing_group_day') ? 1 : 0,
            'consent_terms'       => 1,
            'consent_privacy'     => 1,
            'consent_share'       => $request->input('consent_share') ? 1 : 0,
            'marketing_opt_in'    => $request->input('marketing_opt_in') ? 1 : 0,
            'status'              => $status,
            'verify_token_hash'   => $verifyToken ? hash('sha256', $verifyToken) : null,
            'verified_at'         => $isVerified ? date('Y-m-d H:i:s') : null,
            'source'              => $park ? 'park_qr' : 'web',
            'ip_address'          => $request->ip(),
            'created_at'          => date('Y-m-d H:i:s'),
            'updated_at'          => date('Y-m-d H:i:s'),
        ]);

        $this->saveCategories($requestId, (int) $request->input('primary_category_id'), (array) $request->input('categories', []));
        $imageCount = $this->processImages($requestId, $request);

        RequestWorkflow::recordHistory($requestId, null, $status, $auth->id(), 'Request submitted' . ($imageCount ? " with {$imageCount} image(s)" : ''));
        AuditLog::record('request.created', 'service_request', (string) $requestId, null, $reference);

        \App\Services\Demand\ActivityTracker::record('need_submitted', [
            'request_id'  => $requestId,
            'category_id' => (int) $request->input('primary_category_id') ?: null,
            'town_id'     => (int) $request->input('town_id') ?: null,
            'region_id'   => (int) $request->input('region_id') ?: null,
        ]);

        $email = strtolower(trim((string) $request->input('contact_email')));
        $name = trim((string) $request->input('contact_name'));
        if ($verifyToken !== null) {
            EmailQueue::queueTemplate('email_verification', $email, $name, [
                'customer_name' => $name,
                'action_url'    => url('request/verify?ref=' . $reference . '&token=' . $verifyToken),
            ]);
        } else {
            EmailQueue::queueTemplate('new_request_received', $email, $name, [
                'customer_name'     => $name,
                'request_reference' => $reference,
                'town_name'         => (string) ($request->input('location_label') ?: ''),
            ]);
        }

        Session::flash('request_reference', $reference);
        Session::flash('request_needs_verification', $verifyToken !== null ? '1' : '0');
        return $this->redirect('request-assistance/submitted');
    }

    public function submitted(Request $request): Response
    {
        $reference = Session::pull('request_reference');
        if ($reference === null) {
            return $this->redirect('request-assistance');
        }
        return $this->view('public.request-submitted', [
            'title'             => 'Request received — VanAssist',
            'noindex'           => true,
            'reference'         => (string) $reference,
            'needsVerification' => Session::pull('request_needs_verification') === '1',
        ]);
    }

    public function verify(Request $request): Response
    {
        $reference = (string) $request->input('ref');
        $token = (string) $request->input('token');
        $req = $reference !== '' ? ServiceRequest::findByReference($reference) : null;

        if ($req === null || $token === '' || !hash_equals((string) ($req['verify_token_hash'] ?? ''), hash('sha256', $token))) {
            return $this->view('public.request-verify', [
                'title' => 'Verification link invalid',
                'noindex' => true,
                'ok' => false,
                'reference' => $reference,
            ]);
        }

        if ($req['status'] === 'awaiting_verification') {
            Database::query(
                'UPDATE service_requests SET verified_at = NOW(), verify_token_hash = NULL WHERE id = ?',
                [(int) $req['id']]
            );
            RequestWorkflow::changeStatus((int) $req['id'], 'pending_moderation', null, 'Email verified by customer');
            EmailQueue::queueTemplate('new_request_received', (string) $req['contact_email'], (string) $req['contact_name'], [
                'customer_name'     => (string) $req['contact_name'],
                'request_reference' => (string) $req['reference'],
                'town_name'         => (string) ($req['location_label'] ?? ''),
            ]);
            AuditLog::record('request.verified', 'service_request', (string) $req['id'], null, (string) $req['reference']);
        }

        return $this->view('public.request-verify', [
            'title' => 'Request verified',
            'noindex' => true,
            'ok' => true,
            'reference' => (string) $req['reference'],
        ]);
    }

    // ---- helpers -----------------------------------------------------------

    private function saveCategories(int $requestId, int $primaryId, array $additional): void
    {
        $ids = array_unique(array_filter(array_map('intval', array_merge([$primaryId], $additional))));
        foreach ($ids as $categoryId) {
            if ($categoryId <= 0) {
                continue;
            }
            Database::query(
                'INSERT IGNORE INTO service_request_categories (request_id, category_id, is_primary) VALUES (?, ?, ?)',
                [$requestId, $categoryId, $categoryId === $primaryId ? 1 : 0]
            );
        }
    }

    private function processImages(int $requestId, Request $request): int
    {
        $files = $request->file('images');
        if (!is_array($files) || !isset($files['tmp_name'])) {
            return 0;
        }

        $names = (array) $files['tmp_name'];
        $max = (int) config('uploads.max_request_images', 6);
        $stored = 0;
        $sort = 0;

        for ($i = 0; $i < count($names) && $stored < $max; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $single = [
                'name'     => $files['name'][$i] ?? '',
                'type'     => $files['type'][$i] ?? '',
                'tmp_name' => $files['tmp_name'][$i] ?? '',
                'error'    => $files['error'][$i] ?? UPLOAD_ERR_NO_FILE,
                'size'     => $files['size'][$i] ?? 0,
            ];
            try {
                $meta = ImageProcessor::process($single);
            } catch (RuntimeException) {
                continue; // skip an individual bad/oversized file, keep the request
            }
            Database::query(
                'INSERT INTO service_request_images (request_id, stored_name, thumb_name, mime_type, file_size, width, height, sort_order, created_at) '
                . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
                [$requestId, $meta['stored_name'], $meta['thumb_name'], $meta['mime_type'], $meta['file_size'], $meta['width'], $meta['height'], $sort++]
            );
            $stored++;
        }

        return $stored;
    }
}
