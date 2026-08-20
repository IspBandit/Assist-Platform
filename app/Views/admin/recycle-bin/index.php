<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $items */
/** @var array{entity_type:string,q:string} $filters */
/** @var string|null $error */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="admin-page-intro">
    <div>
        <p class="eyebrow">Directory lifecycle</p>
        <h1>Recycle bin</h1>
        <p class="muted">Soft-deleted providers, stays<?php if (\App\Core\Database::tableExists('traveller_facilities')): ?> and facilities<?php endif; ?> awaiting restore or purge via the Admin API.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('admin/providers')) ?>">Back to providers</a>
</div>

<?php if ($error !== null): ?>
    <div class="alert alert-warning"><?= $this->e($error) ?></div>
<?php endif; ?>

<form method="get" action="<?= e(url('admin/recycle-bin')) ?>" class="card stack" style="margin-top:1rem">
    <div class="grid grid-3">
        <div class="form-group">
            <label for="entity_type">Type</label>
            <select id="entity_type" name="entity_type">
                <option value="">All</option>
                <option value="provider" <?= $filters['entity_type'] === 'provider' ? 'selected' : '' ?>>Providers</option>
                <option value="stay" <?= $filters['entity_type'] === 'stay' ? 'selected' : '' ?>>Stays</option>
                <?php if (\App\Core\Database::tableExists('traveller_facilities')): ?>
                <option value="facility" <?= $filters['entity_type'] === 'facility' ? 'selected' : '' ?>>Facilities</option>
                <?php endif; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="q">Search</label>
            <input type="search" id="q" name="q" value="<?= e_attr($filters['q']) ?>" placeholder="Name">
        </div>
        <div class="form-group" style="align-self:end">
            <button type="submit" class="btn btn-secondary">Filter</button>
        </div>
    </div>
</form>

<div class="card" style="margin-top:1rem">
    <div class="table-wrap">
        <table class="data">
            <thead>
                <tr>
                    <th>Type</th>
                    <th>Name</th>
                    <th>Deleted</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= $this->e((string) ($item['entity_type'] ?? '')) ?></td>
                    <td><?= $this->e((string) ($item['name'] ?? '')) ?><br><span class="muted small"><code><?= $this->e((string) ($item['slug'] ?? '')) ?></code></span></td>
                    <td class="muted small"><?= $this->e((string) ($item['deleted_at'] ?? '')) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('admin/recycle-bin/restore')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="entity_type" value="<?= e_attr((string) ($item['entity_type'] ?? '')) ?>">
                            <input type="hidden" name="id" value="<?= e_attr((string) ($item['id'] ?? '')) ?>">
                            <button type="submit" class="btn btn-primary btn-sm">Restore</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($items === []): ?>
                <tr><td colspan="4" class="muted">Recycle bin is empty.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
