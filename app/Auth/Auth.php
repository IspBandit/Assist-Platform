<?php

declare(strict_types=1);

namespace App\Auth;

use App\Core\Session;
use App\Models\User;

/**
 * Session-backed authentication and role-based access control.
 *
 * The "super-administrator" role bypasses individual permission checks so a
 * super admin can always recover the platform.
 */
final class Auth
{
    private static ?Auth $instance = null;

    private ?array $user = null;
    private ?array $roleSlugs = null;
    private ?array $permissions = null;
    private bool $loaded = false;

    public const SUPER_ADMIN = 'super-administrator';

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function attempt(string $email, string $password): bool
    {
        $user = User::findByEmail($email);
        if ($user === null) {
            return false;
        }
        if (($user['status'] ?? '') === 'suspended') {
            return false;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }

        // Transparently upgrade the hash if PHP's default cost changed.
        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
            User::update((int) $user['id'], [
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        }

        $this->login((int) $user['id'], (int) ($user['auth_version'] ?? 0));
        return true;
    }

    public function login(int $userId, ?int $authVersion = null): void
    {
        if ($authVersion === null) {
            $user = User::find($userId);
            $authVersion = (int) ($user['auth_version'] ?? 0);
        }
        Session::regenerate();
        Session::set('_auth_user_id', $userId);
        Session::set('_auth_version', $authVersion);
        Session::set('_auth_login_time', time());
        Session::set('_auth_last_activity', time());
        $this->reset();
    }

    public function logout(): void
    {
        Session::forget('_auth_user_id');
        Session::forget('_auth_version');
        Session::forget('_auth_login_time');
        Session::forget('_auth_last_activity');
        Session::regenerate();
        $this->reset();
    }

    public function check(): bool
    {
        return $this->user() !== null;
    }

    public function guest(): bool
    {
        return !$this->check();
    }

    public function id(): ?int
    {
        $id = Session::get('_auth_user_id');
        return $id !== null ? (int) $id : null;
    }

    public function user(): ?array
    {
        if ($this->loaded) {
            return $this->user;
        }
        $this->loaded = true;

        $id = Session::get('_auth_user_id');
        if ($id === null) {
            return $this->user = null;
        }

        $user = User::find((int) $id);
        $sessionAuthVersion = (int) Session::get('_auth_version', 0);
        if (
            $user === null
            || ($user['status'] ?? '') === 'suspended'
            || (int) ($user['auth_version'] ?? 0) !== $sessionAuthVersion
        ) {
            $this->logout();
            return $this->user = null;
        }

        return $this->user = $user;
    }

    /** @return array<int,string> */
    public function roles(): array
    {
        if ($this->roleSlugs !== null) {
            return $this->roleSlugs;
        }
        $id = $this->id();
        return $this->roleSlugs = $id !== null ? User::roleSlugs($id) : [];
    }

    public function hasRole(string $slug): bool
    {
        return in_array($slug, $this->roles(), true);
    }

    public function hasAnyRole(string ...$slugs): bool
    {
        return array_intersect($slugs, $this->roles()) !== [];
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(self::SUPER_ADMIN);
    }

    /** @return array<int,string> */
    public function permissions(): array
    {
        if ($this->permissions !== null) {
            return $this->permissions;
        }
        $id = $this->id();
        return $this->permissions = $id !== null ? User::permissions($id) : [];
    }

    public function can(string $permission): bool
    {
        if (!$this->check()) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        return in_array($permission, $this->permissions(), true);
    }

    private function reset(): void
    {
        $this->user = null;
        $this->roleSlugs = null;
        $this->permissions = null;
        $this->loaded = false;
    }
}
