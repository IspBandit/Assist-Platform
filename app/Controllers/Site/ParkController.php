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
            'towns'           => Database::select('SELECT id, name FROM towns WHERE is_active = 1 ORDER BY name'),
            'errors'          => Session::errors(),
        ]);
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
        ]);
    }
}
