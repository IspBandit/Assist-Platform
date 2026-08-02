<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $accounts */
/** @var array<string,array<string,mixed>> $scopesCatalog */
/** @var string|null $unavailable */
/** @var string|null $createdSecret */
/** @var string|null $rotatedSecret */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="admin-page-intro">
    <div>
        <p class="eyebrow">Platform API</p>
        <h1>API service accounts</h1>
        <p class="muted">Machine credentials for <code>/api/v1/admin</code>. Secrets are shown once at creation or rotation.</p>
    </div>
</div>

<?php if ($unavailable !== null): ?>
    <div class="alert alert-warning"><?= $this->e($unavailable) ?></div>
<?php endif; ?>

<?php if ($createdSecret): ?>
    <div class="alert alert-success"><strong>New client secret (copy now):</strong> <code><?= $this->e($createdSecret) ?></code></div>
<?php endif; ?>
<?php if ($rotatedSecret): ?>
    <div class="alert alert-success"><strong>Rotated client secret (copy now):</strong> <code><?= $this->e($rotatedSecret) ?></code></div>
<?php endif; ?>

<div class="grid grid-2" style="margin-top:1rem;align-items:start">
    <div class="card">
        <h2 style="margin-top:0">Create service account</h2>
        <form method="post" action="<?= e(url('admin/api-service-accounts')) ?>" class="stack">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" maxlength="120" required placeholder="Assist RIC staging">
            </div>
            <div class="form-group">
                <label for="scopes">Scopes (comma-separated)</label>
                <textarea id="scopes" name="scopes" rows="4" placeholder="providers:read,stays:read,drafts:write"><?= e(implode(',', \App\Services\Api\AdminApiScopes::RIC_SERVICE)) ?></textarea>
                <p class="muted small">Default RIC least-privilege scopes are prefilled. Human-only scopes are rejected.</p>
            </div>
            <div class="form-group">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <option value="active">Active</option>
                    <option value="disabled">Disabled</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create account</button>
        </form>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Existing accounts</h2>
        <div class="table-wrap">
            <table class="data">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Client key</th>
                        <th>Status</th>
                        <th>Scopes</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($accounts as $account): ?>
                    <tr>
                        <td><?= $this->e((string) ($account['name'] ?? '')) ?></td>
                        <td><code><?= $this->e((string) ($account['client_key'] ?? '')) ?></code></td>
                        <td><?= $this->e((string) ($account['status'] ?? '')) ?></td>
                        <td class="muted small"><?= $this->e(implode(', ', (array) ($account['scopes'] ?? []))) ?></td>
                        <td>
                            <form method="post" action="<?= e(url('admin/api-service-accounts/rotate')) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e_attr((string) ($account['id'] ?? '')) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">Rotate secret</button>
                            </form>
                            <?php if (($account['status'] ?? '') === 'active'): ?>
                            <form method="post" action="<?= e(url('admin/api-service-accounts/disable')) ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= e_attr((string) ($account['id'] ?? '')) ?>">
                                <button type="submit" class="btn btn-ghost btn-sm">Disable</button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($accounts === []): ?>
                    <tr><td colspan="5" class="muted">No service accounts yet.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php $this->endSection(); ?>
