<?php

declare(strict_types=1);

namespace App\Controllers\Provider;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\FoundingGraphicService;
use App\Services\ProviderClaimService;
use App\Services\SubscriptionService;
use App\Validation\Validator;
use Throwable;

/**
 * Self-serve claim flow for unclaimed directory listings.
 */
final class ClaimController extends Controller
{
    public function show(Request $request): Response
    {
        $claim = ProviderClaimService::resolveToken((string) $request->route('token'));
        if ($claim === null) {
            return $this->view('provider.claim-invalid', ['title' => 'Claim link unavailable']);
        }

        $services = Database::select(
            'SELECT c.name FROM provider_services ps JOIN service_categories c ON c.id = ps.category_id '
            . 'WHERE ps.provider_id = ? ORDER BY ps.is_inferred ASC, c.name LIMIT 8',
            [(int) $claim['provider_id']]
        );

        return $this->view('provider.claim-accept', [
            'title'        => 'Claim your listing',
            'token'        => (string) $request->route('token'),
            'email'        => (string) $claim['email'],
            'businessName' => (string) $claim['business_name'],
            'townName'     => (string) ($claim['town_name'] ?? ''),
            'services'     => $services,
            'formErrors'   => Session::errors(),
            'launchOffer'  => !empty($claim['is_launch_town']),
        ]);
    }

    public function store(Request $request): Response
    {
        $token = (string) $request->route('token');
        $claim = ProviderClaimService::resolveToken($token);
        if ($claim === null) {
            return $this->view('provider.claim-invalid', ['title' => 'Claim link unavailable']);
        }

        $validator = Validator::make($request->all(), [
            'contact_name'          => 'required|max:150',
            'password'              => 'required|min:10',
            'password_confirmation' => 'required',
            'consent_terms'         => 'accepted',
        ], ['consent_terms' => 'Provider terms']);

        $formErrors = $validator->fails() ? $validator->errors() : [];
        if (($request->input('password') ?? '') !== ($request->input('password_confirmation') ?? '')) {
            $formErrors['password_confirmation'] = 'Passwords do not match.';
        }

        $email = strtolower(trim((string) $claim['email']));
        $providerId = (int) $claim['provider_id'];
        $contactName = trim((string) $request->input('contact_name'));

        $existing = User::findByEmail($email);
        if ($existing !== null) {
            $linked = Database::selectOne(
                'SELECT id FROM providers WHERE user_id = ? AND deleted_at IS NULL',
                [(int) $existing['id']]
            );
            if ($linked !== null && (int) $linked['id'] !== $providerId) {
                $formErrors['email'] = 'This email is already linked to another provider account. Sign in or contact support.';
            } elseif (!password_verify((string) $request->input('password'), (string) $existing['password_hash'])) {
                $formErrors['email'] = 'An account exists for this email. Sign in with your password, or use Forgot password.';
            }
        }

        if ($formErrors !== []) {
            Session::flashErrors($formErrors);
            return $this->redirect('provider/claim/' . $token);
        }

        try {
            Database::beginTransaction();
            if ($existing !== null) {
                $userId = (int) $existing['id'];
                User::update($userId, ['name' => $contactName, 'updated_at' => date('Y-m-d H:i:s')]);
            } else {
                $userId = User::create([
                    'name'          => $contactName,
                    'email'         => $email,
                    'password_hash' => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
                    'status'        => 'active',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }

            ProviderClaimService::claim((int) $claim['id'], $providerId, $userId, $contactName);
            Database::query(
                'INSERT INTO user_consents (user_id, consent_type, granted, document_version, ip_address, user_agent, created_at) '
                . 'VALUES (?, ?, 1, ?, ?, ?, NOW())',
                [$userId, 'provider_terms', 'provider-terms-v1', $request->ip(), $request->userAgent()]
            );
            AuditLog::record('provider.claimed', 'provider', (string) $providerId, null, (string) $claim['business_name']);
            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            Logger::error('Provider claim failed: ' . $e->getMessage(), ['provider_id' => $providerId], 'errors');
            Session::flash('error', 'We could not complete your claim right now. Please try again.');
            return $this->redirect('provider/claim/' . $token);
        }

        (new SubscriptionService())->provisionProvider($providerId, ['founding' => true]);
        FoundingGraphicService::grantEligibilityIfQualifies($providerId);
        Auth::instance()->login($userId);
        $success = 'Listing claimed. Review your pre-filled profile and complete any missing details.';
        if (FoundingGraphicService::forProvider($providerId) !== null) {
            $success .= ' Once verified, you can request your free local ad graphic from the dashboard.';
        }
        Session::flash('success', $success);
        return $this->redirect('provider');
    }
}
