<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Auth\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\AuditLog;
use App\Services\CsvExport;
use App\Services\EmailQueue;

/**
 * Admin management of user accounts: search/list, profile editing, status
 * (suspend/reactivate), role assignment, password-reset dispatch, soft delete
 * and CSV export. Privilege guards prevent acting on yourself or escalating
 * privileges, and only super-administrators may touch super-admin accounts.
 */
final class UsersController extends Controller
{
    private const STATUSES = ['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'];

    public function index(Request $request): Response
    {
        $this->requirePermission('users.manage');

        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $role = (string) $request->query('role', '');
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 25;

        [$where, $params] = $this->buildFilter($q, $status, $role);
        $total = (int) Database::scalar("SELECT COUNT(*) FROM users u WHERE {$where}", $params);

        $rows = Database::select(
            'SELECT u.id, u.name, u.email, u.phone, u.status, u.last_login_at, u.created_at, '
            . "GROUP_CONCAT(r.name ORDER BY r.level DESC SEPARATOR ', ') AS role_names "
            . 'FROM users u '
            . 'LEFT JOIN user_roles ur ON ur.user_id = u.id '
            . 'LEFT JOIN roles r ON r.id = ur.role_id '
            . "WHERE {$where} GROUP BY u.id ORDER BY u.created_at DESC LIMIT {$perPage} OFFSET " . (($page - 1) * $perPage),
            $params
        );

        return $this->view('admin.users.index', [
            'title'    => 'Users',
            'users'    => $rows,
            'total'    => $total,
            'page'     => $page,
            'perPage'  => $perPage,
            'q'        => $q,
            'status'   => $status,
            'role'     => $role,
            'statuses' => self::STATUSES,
            'roles'    => Database::select('SELECT slug, name FROM roles ORDER BY level DESC'),
        ]);
    }

    public function show(Request $request): Response
    {
        $this->requirePermission('users.manage');
        $user = $this->findOr404((int) $request->query('id', 0));
        $id = (int) $user['id'];

        return $this->view('admin.users.show', [
            'title'        => $user['name'],
            'user'         => $user,
            'roles'        => User::roles($id),
            'consents'     => Database::select(
                'SELECT consent_type, granted, document_version, created_at FROM user_consents '
                . 'WHERE user_id = ? ORDER BY id DESC LIMIT 20',
                [$id]
            ),
            'logins'       => Database::select(
                'SELECT ip_address, user_agent, was_successful, created_at FROM user_login_history '
                . 'WHERE user_id = ? ORDER BY id DESC LIMIT 15',
                [$id]
            ),
            'customer'     => Database::selectOne('SELECT id FROM customers WHERE user_id = ? AND deleted_at IS NULL', [$id]),
            'provider'     => Database::selectOne('SELECT id, business_name FROM providers WHERE user_id = ?', [$id]),
            'canManage'    => $this->canManageTarget($id),
        ]);
    }

    public function form(Request $request): Response
    {
        $this->requirePermission('users.manage');
        $id = (int) $request->query('id', 0);
        $user = $id > 0 ? $this->findOr404($id) : null;

        if ($user !== null && !$this->canManageTarget($id)) {
            return $this->redirectWith('/admin/users/show?id=' . $id, 'error', 'Only a super administrator can edit this account.');
        }

        return $this->view('admin.users.form', [
            'title'        => $user ? 'Edit user' : 'New user',
            'user'         => $user,
            'statuses'     => self::STATUSES,
            'allRoles'     => Database::select('SELECT id, slug, name, level FROM roles ORDER BY level DESC'),
            'userRoleIds'  => $user ? array_map('intval', array_column(User::roles($id), 'id')) : [],
            'isSuperAdmin' => Auth::instance()->hasRole('super-administrator'),
        ]);
    }

    public function save(Request $request): Response
    {
        $this->requirePermission('users.manage');

        $id = (int) $request->input('id', 0);
        $name = trim((string) $request->input('name', ''));
        $email = strtolower(trim((string) $request->input('email', '')));
        $status = (string) $request->input('status', 'active');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->redirectWith('/admin/users', 'error', 'A valid name and email address are required.');
        }
        if (!isset(self::STATUSES[$status])) {
            $status = 'active';
        }

        $emailTaken = (int) Database::scalar(
            'SELECT COUNT(*) FROM users WHERE email = ? AND id <> ? AND deleted_at IS NULL',
            [$email, $id]
        ) > 0;
        if ($emailTaken) {
            return $this->redirectWith('/admin/users', 'error', 'That email address is already in use.');
        }

        $data = [
            'name'             => $name,
            'email'            => $email,
            'phone'            => trim((string) $request->input('phone', '')) ?: null,
            'status'           => $status,
            'marketing_opt_in' => $request->input('marketing_opt_in') ? 1 : 0,
            'internal_notes'   => trim((string) $request->input('internal_notes', '')) ?: null,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($id > 0) {
            if (!$this->canManageTarget($id)) {
                return $this->redirectWith('/admin/users/show?id=' . $id, 'error', 'Only a super administrator can edit this account.');
            }
            User::update($id, $data);
            $this->syncRoles($id, array_map('intval', (array) $request->input('roles', [])));
            AuditLog::record('user.updated', 'user', (string) $id, null, $email);
            return $this->redirectWith('/admin/users/show?id=' . $id, 'success', 'User updated.');
        }

        // New account: generate a random password; the admin then dispatches a
        // reset link so the user sets their own. We never email a raw password.
        $data['password_hash'] = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        $data['created_at'] = date('Y-m-d H:i:s');
        $newId = User::create($data);
        $this->syncRoles($newId, array_map('intval', (array) $request->input('roles', [])));
        AuditLog::record('user.created', 'user', (string) $newId, null, $email);

        if ($request->input('send_reset')) {
            $this->dispatchReset($email, $name);
        }
        return $this->redirectWith('/admin/users/show?id=' . $newId, 'success', 'User created.');
    }

    public function setStatus(Request $request): Response
    {
        $this->requirePermission('users.manage');
        $user = $this->findOr404((int) $request->input('id', 0));
        $id = (int) $user['id'];

        if ($id === Auth::instance()->id()) {
            return $this->redirectWith('/admin/users/show?id=' . $id, 'error', 'You cannot change your own account status.');
        }
        if (!$this->canManageTarget($id)) {
            return $this->redirectWith('/admin/users/show?id=' . $id, 'error', 'Only a super administrator can manage this account.');
        }

        $action = (string) $request->input('action', '');
        $map = ['suspend' => 'suspended', 'reactivate' => 'active'];
        if (!isset($map[$action])) {
            $this->abort(400, 'Unknown action.');
        }
        Database::query('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$map[$action], $id]);
        AuditLog::record('user.status_' . $action, 'user', (string) $id, (string) $user['status'], $map[$action]);
        return $this->redirectWith('/admin/users/show?id=' . $id, 'success', 'User status updated.');
    }

    public function sendReset(Request $request): Response
    {
        $this->requirePermission('users.manage');
        $user = $this->findOr404((int) $request->input('id', 0));
        $this->dispatchReset((string) $user['email'], (string) $user['name']);
        AuditLog::record('user.reset_sent', 'user', (string) $user['id']);
        return $this->redirectWith('/admin/users/show?id=' . (int) $user['id'], 'success', 'Password reset link sent.');
    }

    public function delete(Request $request): Response
    {
        $this->requirePermission('users.manage');
        $user = $this->findOr404((int) $request->input('id', 0));
        $id = (int) $user['id'];

        if ($id === Auth::instance()->id()) {
            return $this->redirectWith('/admin/users/show?id=' . $id, 'error', 'You cannot delete your own account.');
        }
        if (!$this->canManageTarget($id)) {
            return $this->redirectWith('/admin/users/show?id=' . $id, 'error', 'Only a super administrator can delete this account.');
        }
        User::delete($id);
        AuditLog::record('user.deleted', 'user', (string) $id, (string) $user['email']);
        return $this->redirectWith('/admin/users', 'success', 'User deleted.');
    }

    public function export(Request $request): Response
    {
        $this->requirePermission('users.export');
        $q = trim((string) $request->query('q', ''));
        $status = (string) $request->query('status', '');
        $role = (string) $request->query('role', '');
        [$where, $params] = $this->buildFilter($q, $status, $role);

        $rows = Database::select(
            'SELECT u.id, u.name, u.email, u.phone, u.status, u.marketing_opt_in, u.last_login_at, u.created_at, '
            . "GROUP_CONCAT(r.name ORDER BY r.level DESC SEPARATOR '; ') AS roles "
            . 'FROM users u LEFT JOIN user_roles ur ON ur.user_id = u.id LEFT JOIN roles r ON r.id = ur.role_id '
            . "WHERE {$where} GROUP BY u.id ORDER BY u.created_at DESC",
            $params
        );

        AuditLog::record('user.export', 'user', null, null, (string) count($rows));
        return CsvExport::download(
            'users-' . date('Ymd-His') . '.csv',
            ['ID', 'Name', 'Email', 'Phone', 'Status', 'Marketing opt-in', 'Roles', 'Last login', 'Created'],
            array_map(static fn (array $r): array => [
                $r['id'], $r['name'], $r['email'], $r['phone'], $r['status'],
                $r['marketing_opt_in'] ? 'yes' : 'no', $r['roles'], $r['last_login_at'], $r['created_at'],
            ], $rows)
        );
    }

    // --- helpers ------------------------------------------------------------

    /** @return array{0:string,1:array<int,mixed>} */
    private function buildFilter(string $q, string $status, string $role): array
    {
        $where = ['u.deleted_at IS NULL'];
        $params = [];
        if (isset(self::STATUSES[$status])) {
            $where[] = 'u.status = ?';
            $params[] = $status;
        }
        if ($q !== '') {
            $where[] = '(u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like);
        }
        if ($role !== '') {
            $where[] = 'EXISTS (SELECT 1 FROM user_roles ur2 JOIN roles r2 ON r2.id = ur2.role_id WHERE ur2.user_id = u.id AND r2.slug = ?)';
            $params[] = $role;
        }
        return [implode(' AND ', $where), $params];
    }

    private function dispatchReset(string $email, string $name): void
    {
        $token = bin2hex(random_bytes(32));
        $minutes = (int) config('security.password_reset_expiry_minutes', 60);
        Database::query(
            'INSERT INTO password_resets (email, token_hash, expires_at, created_at) '
            . 'VALUES (?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())',
            [$email, hash('sha256', $token), $minutes]
        );
        EmailQueue::queueTemplate('password_reset', $email, $name, [
            'customer_name' => $name,
            'action_url'    => url('reset-password?token=' . $token . '&email=' . urlencode($email)),
        ]);
    }

    /**
     * Replace a user's roles with the supplied set, applying privilege guards:
     * only a super-administrator may grant the super-administrator role, and a
     * non-super-admin cannot alter a super-admin's roles at all.
     *
     * @param array<int,int> $roleIds
     */
    private function syncRoles(int $userId, array $roleIds): void
    {
        $actorIsSuper = Auth::instance()->hasRole('super-administrator');
        if (!$actorIsSuper && !$this->canManageTarget($userId)) {
            return; // never silently downgrade a super-admin
        }

        $valid = Database::select('SELECT id, slug FROM roles');
        $bySlug = [];
        foreach ($valid as $r) {
            $bySlug[(int) $r['id']] = (string) $r['slug'];
        }

        $apply = [];
        foreach ($roleIds as $rid) {
            if (!isset($bySlug[$rid])) {
                continue;
            }
            if ($bySlug[$rid] === 'super-administrator' && !$actorIsSuper) {
                continue; // cannot escalate to super-admin
            }
            $apply[] = $rid;
        }

        Database::query('DELETE FROM user_roles WHERE user_id = ?', [$userId]);
        foreach (array_unique($apply) as $rid) {
            Database::query(
                'INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?, ?, NOW())',
                [$userId, $rid]
            );
        }
    }

    /** A non-super-admin may not manage an account that holds the super-admin role. */
    private function canManageTarget(int $userId): bool
    {
        if (Auth::instance()->hasRole('super-administrator')) {
            return true;
        }
        $targetIsSuper = (int) Database::scalar(
            'SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id = ur.role_id '
            . "WHERE ur.user_id = ? AND r.slug = 'super-administrator'",
            [$userId]
        ) > 0;
        return !$targetIsSuper;
    }

    /** @return array<string,mixed> */
    private function findOr404(int $id): array
    {
        $user = $id > 0 ? User::find($id) : null;
        if ($user === null) {
            $this->abort(404, 'User not found.');
        }
        return $user;
    }
}
