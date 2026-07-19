<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $user */
/** @var array<int,array<string,mixed>> $roles */
/** @var array<int,array<string,mixed>> $consents */
/** @var array<int,array<string,mixed>> $logins */
/** @var array<string,mixed>|null $customer */
/** @var array<string,mixed>|null $provider */
/** @var bool $canManage */
$this->extend('layouts.admin');
$id = (int) $user['id'];
$badge = ['active' => 'badge-verified', 'pending' => 'badge-confirmed', 'suspended' => 'badge-neutral'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between;align-items:flex-start">
        <div>
            <h1 style="margin:0"><?= $this->e((string) $user['name']) ?></h1>
            <p class="muted" style="margin:.25rem 0 0">
                <?= $this->e((string) $user['email']) ?>
                · <span class="badge <?= $badge[$user['status']] ?? 'badge-neutral' ?>"><?= $this->e(ucfirst((string) $user['status'])) ?></span>
            </p>
        </div>
        <div class="btn-row" style="margin:0">
            <a class="btn btn-ghost" href="<?= e(url('admin/users')) ?>">Back to users</a>
            <?php if ($canManage): ?>
                <a class="btn btn-primary" href="<?= e(url('admin/users/edit?id=' . $id)) ?>">Edit</a>
            <?php endif; ?>
        </div>
    </div>
    <?php if (!$canManage): ?>
        <div class="alert alert-info" style="margin-top:1rem">This is a super-administrator account. Only another super administrator can edit, suspend or delete it.</div>
    <?php endif; ?>
</div>

<div class="grid grid-2" style="gap:1rem;align-items:flex-start">
    <div class="card">
        <h2 style="margin:0 0 .5rem">Profile</h2>
        <table class="data">
            <tbody>
                <tr><th style="width:40%">Phone</th><td><?= $this->e((string) ($user['phone'] ?? '—')) ?: '—' ?></td></tr>
                <tr><th>Marketing opt-in</th><td><?= $user['marketing_opt_in'] ? 'Yes' : 'No' ?></td></tr>
                <tr><th>Email verified</th><td><?= $this->e((string) ($user['email_verified_at'] ?? 'No')) ?></td></tr>
                <tr><th>Last login</th><td><?= $this->e((string) ($user['last_login_at'] ?? '—')) ?></td></tr>
                <tr><th>Created</th><td><?= $this->e((string) ($user['created_at'] ?? '—')) ?></td></tr>
                <tr><th>Roles</th><td>
                    <?php if ($roles === []): ?><span class="muted">No roles</span><?php endif; ?>
                    <?php foreach ($roles as $r): ?><span class="badge badge-confirmed" style="margin:0 .25rem .25rem 0"><?= $this->e((string) $r['name']) ?></span><?php endforeach; ?>
                </td></tr>
                <tr><th>Linked accounts</th><td>
                    <?php if ($provider !== null): ?>
                        <a href="<?= e(url('admin/providers/show?id=' . (int) $provider['id'])) ?>">Provider: <?= $this->e((string) $provider['business_name']) ?></a><br>
                    <?php endif; ?>
                    <?php if ($customer !== null): ?>
                        <a href="<?= e(url('admin/customers/show?id=' . (int) $customer['id'])) ?>">Customer profile</a>
                    <?php endif; ?>
                    <?php if ($provider === null && $customer === null): ?><span class="muted">None</span><?php endif; ?>
                </td></tr>
            </tbody>
        </table>
        <?php if ($user['internal_notes']): ?>
            <h3 style="margin:1rem 0 .25rem">Internal notes</h3>
            <p class="muted" style="white-space:pre-wrap"><?= $this->e((string) $user['internal_notes']) ?></p>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2 style="margin:0 0 .5rem">Account actions</h2>
        <?php if ($canManage): ?>
            <div class="btn-row" style="margin:0 0 1rem">
                <?php if ((string) $user['status'] !== 'suspended'): ?>
                    <form method="post" action="<?= e(url('admin/users/status')) ?>" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="suspend">
                        <button type="submit" class="btn btn-secondary">Suspend</button>
                    </form>
                <?php else: ?>
                    <form method="post" action="<?= e(url('admin/users/status')) ?>" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <input type="hidden" name="action" value="reactivate">
                        <button type="submit" class="btn btn-primary">Reactivate</button>
                    </form>
                <?php endif; ?>
                <form method="post" action="<?= e(url('admin/users/send-reset')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-ghost">Send password reset</button>
                </form>
            </div>
            <form method="post" action="<?= e(url('admin/users/delete')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-secondary">Delete user</button>
                <p class="muted" style="font-size:.8rem;margin:.4rem 0 0">Soft delete — the record is retained for audit but the account can no longer sign in.</p>
            </form>
        <?php else: ?>
            <p class="muted">You do not have permission to manage this account.</p>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <h2 style="margin:0 0 .5rem">Consent history</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Type</th><th>Granted</th><th>Document version</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($consents as $c): ?>
                <tr>
                    <td><?= $this->e((string) $c['consent_type']) ?></td>
                    <td><?= $c['granted'] ? 'Yes' : 'No' ?></td>
                    <td class="muted"><?= $this->e((string) ($c['document_version'] ?? '—')) ?></td>
                    <td class="muted"><?= $this->e((string) $c['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($consents === []): ?><tr><td colspan="4" class="muted">No consent records.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2 style="margin:0 0 .5rem">Recent login activity</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>When</th><th>Result</th><th>IP</th><th>Device</th></tr></thead>
            <tbody>
            <?php foreach ($logins as $l): ?>
                <tr>
                    <td class="muted"><?= $this->e((string) $l['created_at']) ?></td>
                    <td><?= $l['was_successful'] ? 'Success' : '<span class="badge badge-neutral">Failed</span>' ?></td>
                    <td class="muted"><?= $this->e((string) ($l['ip_address'] ?? '—')) ?></td>
                    <td class="muted" style="max-width:320px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= $this->e((string) ($l['user_agent'] ?? '—')) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($logins === []): ?><tr><td colspan="4" class="muted">No login history.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
