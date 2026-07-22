<?php

declare(strict_types=1);

namespace App\Controllers\Site;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\CaravanPark;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Validation\Validator;

/**
 * Public caravan-park partner application and the public park page. A park
 * manager applies, which creates their login (caravan-park-partner role) and a
 * park record in `pending` for admin review, then signs them in to the portal.
 */
final class ParkController extends Controller
{
    private const STAY_TYPES = [
        'caravan_park' => 'Caravan park',
        'campground' => 'Campground',
        'free_camp' => 'Free camp',
        'showground' => 'Showground',
        'rest_area' => 'Rest area',
        'farm_stay' => 'Farm stay',
        'other' => 'Other stay',
    ];

    private const PRICE_TYPES = [
        'free' => 'Free',
        'donation' => 'Donation',
        'low_cost' => 'Low cost',
        'paid' => 'Paid',
        'unknown' => 'Check with venue',
    ];

    public function directory(Request $request): Response
    {
        if (current_brand()->id() !== 'vanassist') {
            $this->abort(404, 'Page not found.');
        }

        $townId = filter_var($request->input('town_id'), FILTER_VALIDATE_INT) ?: null;
        $lat = is_numeric($request->input('lat')) ? (float) $request->input('lat') : null;
        $lng = is_numeric($request->input('lng')) ? (float) $request->input('lng') : null;
        if ($lat !== null && ($lat < -90 || $lat > 90)) {
            $lat = null;
        }
        if ($lng !== null && ($lng < -180 || $lng > 180)) {
            $lng = null;
        }
        $stayType = (string) $request->input('stay_type', '');
        $priceType = (string) $request->input('price_type', '');
        $stayType = array_key_exists($stayType, self::STAY_TYPES) ? $stayType : null;
        $priceType = array_key_exists($priceType, self::PRICE_TYPES) ? $priceType : null;

        return $this->view('public.stays', [
            'title' => 'Getting tired? Find a place to stay',
            'metaDescription' => 'Find caravan parks, campgrounds and free or low-cost stays near your town or current location across Australia.',
            'canonical' => url('stays'),
            'stays' => CaravanPark::searchStays($townId, $lat, $lng, $stayType, $priceType),
            'stayTypes' => self::STAY_TYPES,
            'priceTypes' => self::PRICE_TYPES,
            'selectedTownId' => $townId,
            'selectedLocation' => trim((string) $request->input('location', '')),
            'selectedStayType' => $stayType,
            'selectedPriceType' => $priceType,
            'searched' => $townId !== null || ($lat !== null && $lng !== null) || $stayType !== null || $priceType !== null,
        ]);
    }

    public function apply(Request $request): Response
    {
        if (Auth::instance()->check()) {
            $existing = CaravanPark::forUser((int) current_user()['id']);
            if ($existing !== null) {
                return $this->redirect('park');
            }
        }

        return $this->view('public.park-apply', [
            'title'           => 'Become a caravan park partner — VanAssist',
            'metaDescription' => 'Join VanAssist as a caravan park partner. Help your guests find trusted caravan and RV service providers — free during launch.',
            'canonical'       => url('caravan-parks/apply'),
            'towns'           => Database::select("SELECT t.id, CONCAT(t.name, ' / ', s.abbreviation) AS name FROM towns t JOIN states s ON s.id=t.state_id WHERE t.is_active=1 ORDER BY t.name,s.abbreviation"),
            'errors'          => Session::errors(),
        ]);
    }

    public function claim(Request $request): Response
    {
        if (current_brand()->id() !== 'vanassist') {
            $this->abort(404, 'Page not found.');
        }
        $park = CaravanPark::findPublicBySlug((string) $request->route('slug'));
        if ($park === null) {
            $this->abort(404, 'Place to stay not found.');
        }
        return $this->view('public.park-claim', [
            'title' => 'Claim ' . $park['name'],
            'metaDescription' => 'Request management access to this VanAssist stay listing.',
            'canonical' => url('caravan-parks/' . $park['slug'] . '/claim'),
            'park' => $park,
            'errors' => Session::errors(),
        ]);
    }

    public function claimStore(Request $request): Response
    {
        if (current_brand()->id() !== 'vanassist') {
            $this->abort(404, 'Page not found.');
        }
        $park = CaravanPark::findPublicBySlug((string) $request->route('slug'));
        if ($park === null) {
            $this->abort(404, 'Place to stay not found.');
        }
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return $this->redirect('caravan-parks/' . $park['slug']);
        }
        $validator = Validator::make($request->all(), [
            'claimant_name' => 'required|max:150',
            'claimant_email' => 'required|email|max:190',
            'claimant_phone' => 'required|max:40',
            'relationship_to_park' => 'required|max:120',
            'evidence_notes' => 'required|max:2000',
            'consent_terms' => 'accepted',
        ], ['consent_terms' => 'Terms']);
        if ($validator->fails()) {
            Session::flashErrors($validator->errors());
            Session::flashInput($request->all());
            return $this->redirect('caravan-parks/' . $park['slug'] . '/claim');
        }
        $email = strtolower(trim((string) $request->input('claimant_email')));
        $alreadyPending = (int) Database::scalar(
            "SELECT COUNT(*) FROM caravan_park_claims WHERE park_id = ? AND claimant_email = ? AND status = 'pending'",
            [(int) $park['id'], $email]
        );
        if ($alreadyPending === 0) {
            Database::query(
                'INSERT INTO caravan_park_claims (park_id, claimant_name, claimant_email, claimant_phone, relationship_to_park, evidence_notes, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())',
                [(int) $park['id'], trim((string) $request->input('claimant_name')), $email,
                    trim((string) $request->input('claimant_phone')), trim((string) $request->input('relationship_to_park')),
                    trim((string) $request->input('evidence_notes')), 'pending']
            );
            AuditLog::record('park.claim_requested', 'caravan_park', (string) $park['id'], null, $email);
        }
        Session::flash('success', 'Claim received. We will verify your connection to this listing before granting access.');
        return $this->redirect('caravan-parks/' . $park['slug']);
    }

    public function applyStore(Request $request): Response
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return $this->redirect('caravan-parks/apply'); // honeypot
        }

        $data = $request->all();
        $validator = Validator::make($data, [
            'park_name'             => 'required|max:190',
            'contact_name'          => 'required|max:150',
            'email'                 => 'required|email|max:190',
            'password'              => 'required|min:10',
            'password_confirmation' => 'required',
            'consent_terms'         => 'accepted',
        ], [
            'park_name'     => 'Park name',
            'consent_terms' => 'Terms',
        ]);

        $errors = $validator->fails() ? $validator->errors() : [];
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        $email = strtolower(trim((string) $request->input('email')));
        if ($errors === [] && User::findByEmail($email) !== null) {
            $errors['email'] = 'An account already exists for this email. Please sign in instead.';
        }

        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput($data);
            return $this->redirect('caravan-parks/apply');
        }

        $contactName = trim((string) $request->input('contact_name'));
        $parkName = trim((string) $request->input('park_name'));
        $townId = (int) $request->input('town_id') ?: null;
        $town = $townId ? Database::selectOne('SELECT region_id, state_id FROM towns WHERE id = ?', [$townId]) : null;

        $userId = User::create([
            'name'          => $contactName,
            'email'         => $email,
            'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'status'        => 'active',
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
        User::assignRoleBySlug($userId, 'caravan-park-partner');

        $parkId = CaravanPark::create([
            'name'        => $parkName,
            'slug'        => CaravanPark::uniqueSlug($parkName),
            'address'     => trim((string) $request->input('address')) ?: null,
            'town_id'     => $townId,
            'region_id'   => $town['region_id'] ?? null,
            'state_id'    => $town['state_id'] ?? null,
            'phone'       => trim((string) $request->input('phone')) ?: null,
            'email'       => $email,
            'number_of_sites' => (int) $request->input('number_of_sites') ?: null,
            'guest_request_contact' => $email,
            'status'      => 'pending',
            'created_at'  => date('Y-m-d H:i:s'),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);

        Database::query(
            'INSERT INTO caravan_park_users (park_id, user_id, role, created_at) VALUES (?, ?, ?, NOW())',
            [$parkId, $userId, 'owner']
        );

        AuditLog::record('park.applied', 'caravan_park', (string) $parkId, null, $parkName);
        EmailQueue::queueRaw(
            $email,
            $contactName,
            'Your VanAssist caravan park application',
            '<p>Hi ' . e($contactName) . ',</p><p>Thanks for registering <strong>' . e($parkName)
            . '</strong> with VanAssist. Our team will review your application shortly. In the meantime you can complete'
            . ' your park profile from your dashboard.</p>',
            'Thanks for registering ' . $parkName . ' with VanAssist. Our team will review your application shortly.'
        );

        Auth::instance()->login($userId);
        Session::flash('success', 'Welcome to VanAssist. Complete your park profile — our team will review your application shortly.');
        return $this->redirect('park');
    }

    public function show(Request $request): Response
    {
        $park = CaravanPark::findPublicBySlug((string) $request->route('slug'));
        if ($park === null) {
            $this->abort(404, 'Caravan park not found.');
        }
        $id = (int) $park['id'];

        return $this->view('public.park', [
            'title'           => ($park['seo_title'] ?: $park['name']) . ' — VanAssist',
            'metaDescription' => $park['seo_description'] ?: ('Find caravan and RV service near ' . $park['name'] . '.'),
            'canonical'       => url('caravan-parks/' . $park['slug']),
            'park'            => $park,
            'runs'            => CaravanPark::nearbyRuns($park['town_id'] ? (int) $park['town_id'] : null, $park['region_id'] ? (int) $park['region_id'] : null),
            'isManaged'       => (int) Database::scalar('SELECT COUNT(*) FROM caravan_park_users WHERE park_id = ?', [$id]) > 0,
        ]);
    }
}
