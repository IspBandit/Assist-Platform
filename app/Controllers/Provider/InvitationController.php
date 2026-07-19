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
use App\Models\Provider;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\SubscriptionService;
use App\Validation\Validator;
use RuntimeException;
use Throwable;

/**
 * Public-facing provider invitation acceptance. A prospect receives a tokenised
 * link, sets a password and their provider profile + login are created. The new
 * provider starts in `pending` for admin review and is provisioned with the
 * (dormant) billing records as a founding provider.
 */
final class InvitationController extends Controller
{
    public function accept(Request $request): Response
    {
        $invitation = $this->resolveInvitation((string) $request->route('token'));
        if ($invitation === null) {
            return $this->view('provider.invite-invalid', ['title' => 'Invitation unavailable']);
        }

        return $this->inviteForm($request, $invitation, Session::errors());
    }

    public function store(Request $request): Response
    {
        $token = (string) $request->route('token');
        $invitation = $this->resolveInvitation($token);
        if ($invitation === null) {
            return $this->view('provider.invite-invalid', ['title' => 'Invitation unavailable']);
        }

        $data = $request->all();
        $validator = Validator::make($data, [
            'contact_name'          => 'required|max:150',
            'business_name'         => 'required|max:190',
            'password'              => 'required|min:10',
            'password_confirmation' => 'required',
            'consent_terms'         => 'accepted',
        ], ['consent_terms' => 'Provider terms']);

        $formErrors = $validator->fails() ? $validator->errors() : [];
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $formErrors['password_confirmation'] = 'Passwords do not match.';
        }

        $email = strtolower(trim((string) $invitation['email']));
        $existingUserId = null;
        $existing = User::findByEmail($email);
        if ($existing !== null) {
            $linkedProvider = Database::selectOne(
                'SELECT id FROM providers WHERE user_id = ? AND deleted_at IS NULL',
                [(int) $existing['id']]
            );
            if ($linkedProvider !== null) {
                $formErrors['email'] = 'An account already exists for this email. Please sign in instead.';
            } elseif (!password_verify((string) $request->input('password'), (string) $existing['password_hash'])) {
                $formErrors['email'] = 'An account exists for this email but your provider profile is not finished. '
                    . 'Sign in with the password you chose earlier, or use Forgot password on the login page.';
            } else {
                $existingUserId = (int) $existing['id'];
            }
        }

        $input = [
            'business_name' => trim((string) $request->input('business_name')),
            'contact_name'  => trim((string) $request->input('contact_name')),
        ];

        if ($formErrors !== []) {
            Logger::info('Provider invitation form validation failed.', [
                'email'  => $email,
                'errors' => $formErrors,
            ], 'app');
            return $this->inviteForm($request, $invitation, $formErrors, $input, 422);
        }

        $contactName = $input['contact_name'];
        $businessName = $input['business_name'];

        try {
            Database::beginTransaction();

            if ($existingUserId !== null) {
                $userId = $existingUserId;
                User::update($userId, [
                    'name'       => $contactName,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
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

            User::assignRoleBySlug($userId, 'provider');
            if (!in_array('provider', User::roleSlugs($userId), true)) {
                throw new RuntimeException('The provider role is not configured on this site.');
            }

            $existingUnclaimed = Database::selectOne(
                'SELECT id FROM providers WHERE is_unclaimed = 1 AND deleted_at IS NULL AND LOWER(email) = ? LIMIT 1',
                [$email]
            );
            if (!empty($invitation['provider_id'])) {
                $providerId = (int) $invitation['provider_id'];
                Database::query(
                    'UPDATE providers SET user_id = ?, contact_name = ?, business_name = ?, is_unclaimed = 0, claimed_at = NOW(), status = \'pending\', updated_at = NOW() WHERE id = ?',
                    [$userId, $contactName, $businessName, $providerId]
                );
            } elseif ($existingUnclaimed !== null) {
                $providerId = (int) $existingUnclaimed['id'];
                Database::query(
                    'UPDATE providers SET user_id = ?, contact_name = ?, business_name = ?, is_unclaimed = 0, claimed_at = NOW(), status = \'pending\', updated_at = NOW() WHERE id = ?',
                    [$userId, $contactName, $businessName, $providerId]
                );
            } else {
                $providerId = Provider::create([
                    'user_id'       => $userId,
                    'business_name' => $businessName,
                    'slug'          => $this->uniqueSlug($businessName),
                    'contact_name'  => $contactName,
                    'email'         => $email,
                    'status'        => 'pending',
                    'created_at'    => date('Y-m-d H:i:s'),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
            }

            Database::query(
                'INSERT INTO user_consents (user_id, consent_type, granted, document_version, ip_address, user_agent, created_at) '
                . 'VALUES (?, ?, 1, ?, ?, ?, NOW())',
                [$userId, 'provider_terms', 'provider-terms-v1', $request->ip(), $request->userAgent()]
            );

            Database::query(
                'UPDATE provider_invitations SET provider_id = ?, accepted_at = NOW() WHERE id = ?',
                [$providerId, (int) $invitation['id']]
            );
            if ($invitation['prospect_id']) {
                Database::query(
                    "UPDATE provider_prospects SET outreach_status = 'registered', provider_id = ?, updated_at = NOW() WHERE id = ?",
                    [$providerId, (int) $invitation['prospect_id']]
                );
            }

            AuditLog::record('provider.invitation_accepted', 'provider', (string) $providerId, null, $businessName);

            Database::commit();
        } catch (Throwable $e) {
            Database::rollBack();
            Logger::error('Provider invitation signup failed: ' . $e->getMessage(), [
                'email'     => $email,
                'exception' => get_class($e),
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
            ], 'errors');
            return $this->inviteForm($request, $invitation, [
                'form' => 'We could not create your provider profile right now. Please try again in a moment, or contact VanAssist support if this keeps happening.',
            ], $input, 422);
        }

        (new SubscriptionService())->provisionProvider($providerId, ['founding' => true]);

        Auth::instance()->login($userId);
        Session::flash('success', 'Welcome to VanAssist. Complete your profile — our team will review it shortly.');
        return $this->redirect('provider');
    }

    /**
     * @param array<string,mixed> $invitation
     * @param array<string,string> $formErrors
     * @param array<string,string> $input
     */
    private function inviteForm(
        Request $request,
        array $invitation,
        array $formErrors = [],
        array $input = [],
        int $status = 200
    ): Response {
        $prospect = $invitation['prospect_id']
            ? Database::selectOne('SELECT business_name, contact_name, base_town_id FROM provider_prospects WHERE id = ?', [(int) $invitation['prospect_id']])
            : null;
        $linkedProvider = !empty($invitation['provider_id'])
            ? Database::selectOne(
                'SELECT p.business_name, p.contact_name, t.name AS town_name FROM providers p LEFT JOIN towns t ON t.id = p.base_town_id WHERE p.id = ?',
                [(int) $invitation['provider_id']]
            )
            : null;

        return $this->view('provider.invite-accept', [
            'title'        => 'Create your provider profile',
            'token'        => (string) $request->route('token'),
            'email'        => (string) $invitation['email'],
            'businessName' => (string) ($input['business_name'] ?? $linkedProvider['business_name'] ?? $prospect['business_name'] ?? ''),
            'contactName'  => (string) ($input['contact_name'] ?? $linkedProvider['contact_name'] ?? $prospect['contact_name'] ?? ''),
            'townName'     => (string) ($linkedProvider['town_name'] ?? ''),
            'isClaim'      => $linkedProvider !== null,
            'formErrors'   => $formErrors,
        ], $status);
    }

    /** @return array<string,mixed>|null */
    private function resolveInvitation(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        return Database::selectOne(
            'SELECT * FROM provider_invitations WHERE token_hash = ? AND accepted_at IS NULL AND expires_at > NOW()',
            [hash('sha256', $token)]
        );
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
