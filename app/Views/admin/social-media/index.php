<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="page-heading">
    <div><p class="eyebrow">Brand content</p><h1>Social Studio</h1><p class="lead">Generate, review and download premium <?= $this->e($brand->name()) ?> artwork and ready-to-use post copy.</p></div>
</div>

<?php if (!$schemaReady): ?>
    <div class="alert alert-warning"><strong>Setup required.</strong> Run database migration 039 to enable the Social Studio.</div>
<?php else: ?>
<section class="card social-studio-create">
    <div><h2>Create a campaign asset</h2><p class="muted">Start with a controlled brand template, campaign purpose and exact channel dimensions. Every output remains a separate production PNG.</p></div>
    <form method="post" action="<?= e(url('admin/social-media/generate')) ?>" class="social-studio-form">
        <?= csrf_field() ?>
        <label>Campaign name<input type="text" name="campaign_name" maxlength="120" placeholder="e.g. Provider launch — August 2026"></label>
        <label>Purpose<span class="required">*</span><select name="intention" required><?php foreach ($intentions as $key => $label): ?><option value="<?= e($key) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
        <label>Design system<span class="required">*</span><select name="template_key" required><?php foreach ($templates as $key => $label): ?><option value="<?= e($key) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
        <label>Platform and format<span class="required">*</span><select name="format_key" required><?php foreach ($formats as $key => $format): ?><option value="<?= e($key) ?>"><?= $this->e($format['label']) ?> — <?= (int) $format['width'] ?>×<?= (int) $format['height'] ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-primary" type="submit">Generate premium asset</button>
    </form>
</section>

<section class="section-compact">
    <div class="section-heading"><div><p class="eyebrow">Review library</p><h2><?= count($assets) ?> generated asset<?= count($assets) === 1 ? '' : 's' ?></h2></div></div>
    <?php if ($assets === []): ?>
        <div class="empty-state"><h3>Your social library is ready</h3><p>Choose a campaign purpose and format above to create the first graphic.</p></div>
    <?php else: ?>
        <div class="social-asset-grid">
        <?php foreach ($assets as $asset): ?>
            <article class="card social-asset-card">
                <a class="social-preview" href="<?= e(url('admin/social-media/preview?id=' . (int) $asset['id'])) ?>" target="_blank" rel="noopener">
                    <img src="<?= e(url('admin/social-media/preview?id=' . (int) $asset['id'])) ?>" alt="<?= $this->e($asset['headline']) ?> preview" loading="lazy">
                </a>
                <div class="social-asset-body">
                    <div class="social-meta"><span class="badge"><?= $this->e(ucfirst((string) $asset['platform'])) ?></span><span class="badge badge-muted"><?= $this->e(ucwords(str_replace('-', ' ', (string) ($asset['template_key'] ?? 'editorial')))) ?></span><span class="badge badge-muted"><?= (int) $asset['width'] ?>×<?= (int) $asset['height'] ?></span><span class="badge status-<?= e((string) $asset['status']) ?>"><?= $this->e(ucfirst((string) $asset['status'])) ?></span></div>
                    <?php if (!empty($asset['campaign_name'])): ?><p class="eyebrow"><?= $this->e($asset['campaign_name']) ?></p><?php endif; ?>
                    <h3><?= $this->e($asset['headline']) ?></h3>
                    <label>Post copy<textarea class="social-caption" rows="7" readonly><?= $this->e($asset['caption']) ?></textarea></label>
                    <div class="btn-row">
                        <a class="btn btn-primary" href="<?= e(url('admin/social-media/download?id=' . (int) $asset['id'])) ?>">Download PNG</a>
                        <?php if ($asset['status'] !== 'approved'): ?><form method="post" action="<?= e(url('admin/social-media/status')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $asset['id'] ?>"><input type="hidden" name="status" value="approved"><button class="btn btn-secondary" type="submit">Approve</button></form><?php endif; ?>
                        <?php if ($asset['status'] !== 'archived'): ?><form method="post" action="<?= e(url('admin/social-media/status')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $asset['id'] ?>"><input type="hidden" name="status" value="archived"><button class="btn btn-ghost" type="submit">Archive</button></form><?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php endif; ?>
<?php $this->endSection(); ?>
