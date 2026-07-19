<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\EmailQueue;
use App\Services\RateLimiter;
use Throwable;

final class PasswordController extends Controller
{
    public function showForgot(Request $request): Response
    {
        return $this->view('auth.forgot', ['title' => 'Reset your password']);
    }

    public function forgot(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $rateLimitSubjects = ['email:' . $email, 'ip:' . $request->ip()];
        $blocked = RateLimiter::blocked('auth.password.forgot', $rateLimitSubjects);
        $user = $email !== '' ? User::findByEmail($email) : null;

        if ($user !== null && !$blocked) {
            $token = bin2hex(random_bytes(32));
            $minutes = (int) config('security.password_reset_expiry_minutes', 60);
            Database::query(
                'INSERT INTO password_resets (email, token_hash, expires_at, created_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())',
                [$email, hash('sha256', $token), $minutes]
            );
            EmailQueue::queueTemplate('password_reset', $email, (string) $user['name'], [
                'customer_name' => (string) $user['name'],
                'action_url'    => url('reset-password?token=' . $token . '&email=' . urlencode($email)),
            ]);
            AuditLog::record('password.reset_requested', 'user', (string) $user['id']);
        }
        if (!$blocked) {
            RateLimiter::hit('auth.password.forgot', $rateLimitSubjects, 5, 3600, 3600);
        }

        // Generic response to avoid revealing whether the email exists.
        Session::flash('success', 'If that email is registered, a password reset link has been sent.');
        return $this->redirect('login');
    }

    public function showReset(Request $request): Response
    {
        return $this->view('auth.reset', [
            'title' => 'Choose a new password',
            'token' => (string) $request->query('token'),
            'email' => (string) $request->query('email'),
            'errors' => Session::errors(),
        ]);
    }

    public function reset(Request $request): Response
    {
        $email = strtolower(trim((string) $request->input('email')));
        $token = (string) $request->input('token');
        $password = (string) $request->input('password');
        $rateLimitSubjects = ['email:' . $email, 'ip:' . $request->ip()];

        $errors = [];
        if (RateLimiter::blocked('auth.password.reset', $rateLimitSubjects)) {
            $errors['token'] = 'Too many reset attempts. Please wait and request a new link.';
        }
        if (strlen($password) < 10) {
            $errors['password'] = 'Password must be at least 10 characters.';
        }
        if ($password !== $request->input('password_confirmation')) {
            $errors['password_confirmation'] = 'Passwords do not match.';
        }

        $record = Database::selectOne(
            'SELECT * FROM password_resets WHERE email = ? AND token_hash = ? AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            [$email, hash('sha256', $token)]
        );
        if ($record === null) {
            $errors['token'] = 'This reset link is invalid or has expired. Please request a new one.';
            RateLimiter::hit('auth.password.reset', $rateLimitSubjects, 10, 900, 900);
        }

        if ($errors !== []) {
            Session::flashErrors($errors);
            return $this->redirect('reset-password?token=' . urlencode($token) . '&email=' . urlencode($email));
        }

        $user = User::findByEmail($email);
        if ($user !== null) {
            Database::beginTransaction();
            try {
                User::update((int) $user['id'], [
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'updated_at'    => date('Y-m-d H:i:s'),
                ]);
                Database::query(
                    'UPDATE users SET auth_version = auth_version + 1 WHERE id = ?',
                    [(int) $user['id']]
                );
                Database::query(
                    'UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL',
                    [$email]
                );
                Database::commit();
            } catch (Throwable $e) {
                Database::rollBack();
                throw $e;
            }
            RateLimiter::clear('auth.password.reset', $rateLimitSubjects);
            AuditLog::record('password.reset', 'user', (string) $user['id']);
        }

        Session::flash('success', 'Your password has been reset. Please sign in.');
        return $this->redirect('login');
    }
}
