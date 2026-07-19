<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\RateLimiter;
use App\Validation\Validator;

final class AuthController extends Controller
{
    public function showLogin(Request $request): Response
    {
        return $this->view('auth.login', ['title' => 'Sign in', 'errors' => Session::errors()]);
    }

    public function login(Request $request): Response
    {
        // Honeypot: bots fill hidden fields.
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return $this->redirect('login');
        }

        $email = strtolower(trim((string) $request->input('email')));
        $rateLimitSubjects = ['email:' . $email, 'ip:' . $request->ip()];

        if (RateLimiter::blocked('auth.login', $rateLimitSubjects) || $this->isLockedOut()) {
            Session::flash('error', 'Too many attempts. Please wait a few minutes and try again.');
            return $this->redirect('login');
        }

        $password = (string) $request->input('password');

        $auth = Auth::instance();
        if ($auth->attempt($email, $password)) {
            RateLimiter::clear('auth.login', $rateLimitSubjects);
            $this->clearAttempts();
            $user = User::findByEmail($email);
            if ($user !== null) {
                User::recordLogin((int) $user['id'], $request->ip(), $request->userAgent(), true);
                User::touchLastLogin((int) $user['id']);
                AuditLog::record('auth.login', 'user', (string) $user['id']);
            }
            return $this->redirect($this->intendedDestination());
        }

        $this->registerFailedAttempt();
        $maxAttempts = (int) config('security.login.max_attempts', 5);
        $lockSeconds = max(60, (int) config('security.login.lockout_minutes', 15) * 60);
        RateLimiter::hit(
            'auth.login',
            $rateLimitSubjects,
            $maxAttempts,
            $lockSeconds,
            $lockSeconds
        );
        $known = User::findByEmail($email);
        if ($known !== null) {
            User::recordLogin((int) $known['id'], $request->ip(), $request->userAgent(), false);
        }

        Session::flash('error', 'Those credentials do not match our records.');
        Session::flashInput(['email' => $email]);
        return $this->redirect('login');
    }

    public function logout(Request $request): Response
    {
        AuditLog::record('auth.logout', 'user', (string) (Auth::instance()->id() ?? ''));
        Auth::instance()->logout();
        Session::flash('success', 'You have been signed out.');
        return $this->redirect('/');
    }

    public function showRegister(Request $request): Response
    {
        return $this->view('auth.register', ['title' => 'Create an account', 'errors' => Session::errors()]);
    }

    public function register(Request $request): Response
    {
        if ($request->input('website') !== null && $request->input('website') !== '') {
            return $this->redirect('register');
        }

        $data = $request->all();
        $validator = Validator::make($data, [
            'name'             => 'required|max:150',
            'email'            => 'required|email|max:190',
            'password'         => 'required|min:10',
            'password_confirmation' => 'required',
            'consent_terms'    => 'accepted',
            'consent_privacy'  => 'accepted',
        ], ['consent_terms' => 'Terms', 'consent_privacy' => 'Privacy policy']);

        $errors = [];
        if ($validator->fails()) {
            $errors = $validator->errors();
        }
        if (($data['password'] ?? '') !== ($data['password_confirmation'] ?? '')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        $email = strtolower(trim((string) $request->input('email')));
        if (!isset($errors['email']) && User::findByEmail($email) !== null) {
            $errors['email'] = 'An account with this email already exists.';
        }

        if ($errors !== []) {
            Session::flashErrors($errors);
            Session::flashInput(['name' => $request->input('name'), 'email' => $email]);
            return $this->redirect('register');
        }

        $userId = User::create([
            'name'             => trim((string) $request->input('name')),
            'email'            => $email,
            'phone'            => $request->input('phone') ?: null,
            'password_hash'    => password_hash((string) $request->input('password'), PASSWORD_DEFAULT),
            'status'           => 'active',
            'marketing_opt_in' => $request->input('marketing_opt_in') === '1' ? 1 : 0,
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);
        User::assignRoleBySlug($userId, 'customer');

        // Create the customer profile record.
        \App\Core\Database::query(
            'INSERT INTO customers (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())',
            [$userId]
        );

        $this->recordConsents($userId, $request);
        $this->sendVerificationEmail($userId, trim((string) $request->input('name')), $email);

        AuditLog::record('user.register', 'user', (string) $userId);

        Auth::instance()->login($userId);
        Session::flash('success', 'Welcome to VanAssist. Please check your email to verify your address.');
        return $this->redirect('account');
    }

    // ------------------------------------------------------------------

    private function recordConsents(int $userId, Request $request): void
    {
        foreach (['terms', 'privacy', 'marketing'] as $type) {
            $granted = $request->input('consent_' . $type) === '1' || ($type === 'marketing' && $request->input('marketing_opt_in') === '1');
            \App\Core\Database::query(
                'INSERT INTO user_consents (user_id, consent_type, granted, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())',
                [$userId, $type, $granted ? 1 : 0, $request->ip(), $request->userAgent()]
            );
        }
    }

    private function sendVerificationEmail(int $userId, string $name, string $email): void
    {
        $token = bin2hex(random_bytes(32));
        $hours = (int) config('security.email_verification_expiry_hours', 48);
        \App\Core\Database::query(
            'INSERT INTO email_verifications (user_id, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? HOUR), NOW())',
            [$userId, hash('sha256', $token), $hours]
        );

        EmailQueue::queueTemplate('email_verification', $email, $name, [
            'customer_name' => $name,
            'action_url'    => url('verify-email?token=' . $token . '&id=' . $userId),
        ]);
    }

    private function intendedDestination(): string
    {
        $intended = Session::pull('_intended_url');
        if (is_string($intended) && $intended !== '' && !str_contains($intended, 'login')) {
            return $intended;
        }
        $auth = Auth::instance();
        if ($auth->hasAnyRole('administrator', 'moderator', Auth::SUPER_ADMIN)) {
            return 'admin';
        }
        if ($auth->hasRole('provider')) {
            return 'provider';
        }
        if ($auth->hasRole('caravan-park-partner')) {
            return 'park';
        }
        return 'account';
    }

    private function attemptKey(): string
    {
        return '_login_attempts';
    }

    private function isLockedOut(): bool
    {
        $until = (int) Session::get('_login_locked_until', 0);
        return $until > time();
    }

    private function registerFailedAttempt(): void
    {
        $max = (int) config('security.login.max_attempts', 5);
        $attempts = (int) Session::get($this->attemptKey(), 0) + 1;
        Session::set($this->attemptKey(), $attempts);
        if ($attempts >= $max) {
            $lockMinutes = (int) config('security.login.lockout_minutes', 15);
            Session::set('_login_locked_until', time() + $lockMinutes * 60);
            Session::set($this->attemptKey(), 0);
        }
    }

    private function clearAttempts(): void
    {
        Session::forget($this->attemptKey());
        Session::forget('_login_locked_until');
    }
}
