<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $users */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $q */
/** @var string $status */
/** @var string $role */
/** @var array<string,string> $statuses */
/** @var array<int,array<string,mixed>> $roles */
$this->extend('layouts.admin');
$pages = (int) ceil(max(1, $total) / $perPage);
$badge = ['active' => 'badge-verified', 'pending' => 'badge-confirmed', 'suspended' => 'badge-neutral'];
$qs = static function (array $extra) use ($q, $status, $role): string {
    $params = array_filter(['q' => $q, 'status' => $status, 'role' => $role] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:center">
        <h1 style="margin:0">Users <span class="muted" style="font-size:1rem">(<?= (int) $total ?>)</span></h1>
        <div class="btn-row" style="margin:0">
            <?php if (can('users.export')): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/users/export' . $qs([]))) ?>">Export CSV</a>
            <?php endif; ?>
            <a class="btn btn-primary" href="<?= e(url('admin/users/new')) ?>">New user</a>
        </div>
    </div>
    <form method="get" action="<?= e(url('admin/users')) ?>" class="grid grid-3" style="margin-top:1rem;align-items:flex-end">
        <div class="form-group mb-0">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $value => $label): ?>
                    <option value="<?= e((string) $value) ?>" <?= $status === (string) $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="role">Role</label>
            <select id="role" name="role">
                <option value="">All roles</option>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= e((string) $r['slug']) ?>" <?= $role === (string) $r['slug'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group mb-0">
            <label for="q">Search</label>
            <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="Name, email, phone">
        </div>
        <div class="form-group mb-0">
            <button class="btn btn-secondary" type="submit">Filter</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Name</th><th>Email</th><th>Roles</th><th>Status</th><th>Last login</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?= $this->e((string) $u['name']) ?></strong></td>
                    <td><?= $this->e((string) $u['email']) ?></td>
                    <td class="muted"><?= $this->e((string) ($u['role_names'] ?? '—')) ?: '—' ?></td>
                    <td><span class="badge <?= $badge[$u['status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $u['status'])) ?></span></td>
                    <td class="muted"><?= $this->e((string) ($u['last_login_at'] ?? '—')) ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/users/show?id=' . (int) $u['id'])) ?>">Manage</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($users === []): ?>
                <tr><td colspan="6" class="muted">No users found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
        <div class="btn-row" style="margin-top:1rem">
            <?php if ($page > 1): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/users' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a>
            <?php endif; ?>
            <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
            <?php if ($page < $pages): ?>
                <a class="btn btn-ghost" href="<?= e(url('admin/users' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
