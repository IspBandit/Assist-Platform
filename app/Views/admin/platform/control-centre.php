<?php
/** @var \App\Core\View $this */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="page-heading platform-heading">
    <div><p class="eyebrow">All brands</p><h1>Assist Platform control centre</h1><p class="lead">One operational view of the saleable platform, its four brands and shared services.</p></div>
    <a class="btn btn-secondary" href="<?= e(url('admin/brand-builder')) ?>">Open Brand Builder</a>
</div>

<div class="stat-grid">
    <div class="stat"><div class="num"><?= (int) $totals['users'] ?></div><div class="label">Platform users</div></div>
    <div class="stat"><div class="num"><?= (int) $totals['providers'] ?></div><div class="label">Canonical providers</div></div>
    <div class="stat"><div class="num"><?= (int) $totals['memberships'] ?></div><div class="label">Active memberships</div></div>
    <div class="stat"><div class="num"><?= (int) $totals['queued_email'] ?></div><div class="label">Queued email</div></div>
    <div class="stat"><div class="num"><?= (int) $totals['failed_email'] ?></div><div class="label">Failed email</div></div>
</div>

<section class="section-compact">
    <div class="section-heading"><div><p class="eyebrow">Brand portfolio</p><h2>Operate every tenant without signing in again</h2></div></div>
    <div class="platform-brand-grid">
        <?php foreach ($brands as $key => $brand): $theme = $brand->theme(); $assets = $brand->assets(); ?>
        <article class="card platform-brand-card" style="--tenant-colour:<?= e($theme['brand'] ?? '#0f6e6e') ?>">
            <div class="platform-brand-title"><img src="<?= e($assets['logo'] ?? '/assets/brands/vanassist/mark.svg') ?>" alt="" width="48" height="48"><div><h3><?= $this->e($brand->name()) ?></h3><p class="muted"><?= $this->e($brand->status()) ?> · <?= $this->e($brand->primaryDomain()) ?></p></div></div>
            <dl class="platform-brand-metrics"><div><dt>Listings</dt><dd><?= (int) $brandStats[$key]['providers'] ?></dd></div><div><dt>Categories</dt><dd><?= (int) $brandStats[$key]['categories'] ?></dd></div><div><dt>Social assets</dt><dd><?= (int) $brandStats[$key]['assets'] ?></dd></div></dl>
            <form method="post" action="<?= e(url('admin/switch-brand')) ?>"><?= csrf_field() ?><input type="hidden" name="brand" value="<?= e($key) ?>"><input type="hidden" name="return_path" value="/admin"><button class="btn btn-primary" type="submit">Open <?= $this->e($brand->name()) ?> dashboard</button></form>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="grid grid-2 section-compact">
    <section class="card"><h2>Shared platform services</h2><ul class="control-centre-list"><li>Identity, sessions and scoped RBAC</li><li>Canonical provider records and per-brand listings</li><li>Memberships, entitlements, billing and finance</li><li>Reviews, search, maps and analytics</li><li>CMS, email campaigns, notifications and Social Studio</li><li>Deployments, backups, queues, logs and audits</li></ul><p><a href="<?= e(url('admin/brand-builder')) ?>">Validate a private brand blueprint</a></p></section>
    <section class="card"><h2>Scheduled operations</h2><?php if ($tasks === []): ?><p class="muted">No task status is available.</p><?php else: ?><div class="table-wrap"><table class="data"><thead><tr><th>Task</th><th>Status</th><th>Last run</th></tr></thead><tbody><?php foreach ($tasks as $task): ?><tr><td><?= $this->e($task['task_key']) ?></td><td><?= $this->e($task['last_status']) ?></td><td><?= $this->e((string) ($task['last_run_at'] ?? 'Never')) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section>
</div>
<?php $this->endSection(); ?>
