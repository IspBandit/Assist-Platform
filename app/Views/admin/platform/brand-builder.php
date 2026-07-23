<?php
/** @var \App\Core\View $this */
/** @var array<string,string> $moduleOptions */
/** @var array<string,mixed>|null $blueprint */
$this->extend('layouts.admin');
$values = is_array($values ?? null) ? $values : [];
?>
<?php $this->section('content'); ?>
<div class="page-heading platform-heading">
    <div>
        <p class="eyebrow">Platform Control Centre</p>
        <h1>Brand Builder</h1>
        <p class="lead">Design and validate a private tenant blueprint. Previewing never changes DNS, production routing or public launch state.</p>
    </div>
</div>

<?php if (!empty($error)): ?><div class="alert alert-error" role="alert"><?= $this->e((string) $error) ?></div><?php endif; ?>

<div class="grid grid-2">
    <section class="card">
        <h2>1. Brand identity and modules</h2>
        <form method="post" action="<?= e(url('admin/brand-builder/preview')) ?>">
            <?= csrf_field() ?>
            <div class="form-group"><label for="brand_key">Brand key</label><input id="brand_key" name="brand_key" required maxlength="40" pattern="[a-z][a-z0-9-]{2,39}" value="<?= $this->e((string) ($values['brand_key'] ?? '')) ?>"><small>Stable lowercase identifier, for example machine-assist.</small></div>
            <div class="form-group"><label for="name">Public name</label><input id="name" name="name" required maxlength="120" value="<?= $this->e((string) ($values['name'] ?? '')) ?>"></div>
            <div class="form-group"><label for="domain">Intended primary domain</label><input id="domain" name="domain" required inputmode="url" placeholder="example.com.au" value="<?= $this->e((string) ($values['domain'] ?? '')) ?>"><small>Hostname only. Ownership and DNS are verified separately.</small></div>
            <div class="grid grid-2">
                <div class="form-group"><label for="primary_colour">Primary colour</label><input id="primary_colour" name="primary_colour" type="color" value="<?= $this->e((string) ($values['primary_colour'] ?? '#0f6e6e')) ?>"></div>
                <div class="form-group"><label for="accent_colour">Accent colour</label><input id="accent_colour" name="accent_colour" type="color" value="<?= $this->e((string) ($values['accent_colour'] ?? '#e56b2f')) ?>"></div>
            </div>
            <fieldset><legend>Platform modules</legend>
                <?php $selected = is_array($values['modules'] ?? null) ? $values['modules'] : []; foreach ($moduleOptions as $key => $label): ?>
                    <label class="check-row"><input type="checkbox" name="modules[]" value="<?= e($key) ?>" <?= in_array($key, $selected, true) ? 'checked' : '' ?>> <?= $this->e($label) ?></label>
                <?php endforeach; ?>
            </fieldset>
            <button class="btn btn-primary" type="submit">Validate private blueprint</button>
        </form>
    </section>

    <section class="card">
        <h2>2. Reviewed promotion</h2>
        <?php if ($blueprint === null): ?>
            <p class="muted">Complete the form to preview the tenant manifest and its launch prerequisites.</p>
        <?php else: ?>
            <p><span class="badge badge-neutral">Private preview</span> <strong><?= $this->e((string) $blueprint['name']) ?></strong> passed blueprint validation.</p>
            <dl class="platform-brand-metrics"><div><dt>Key</dt><dd><?= $this->e((string) $blueprint['brand_key']) ?></dd></div><div><dt>Domain</dt><dd><?= $this->e((string) $blueprint['domains']['primary']) ?></dd></div><div><dt>Status</dt><dd>private</dd></div></dl>
            <h3>Enabled modules</h3><ul><?php foreach ($blueprint['modules'] as $key => $enabled): if ($enabled): ?><li><?= $this->e($moduleOptions[$key] ?? $key) ?></li><?php endif; endforeach; ?></ul>
            <h3>Required before configuration or launch</h3><ol><?php foreach ($blueprint['launch_prerequisites'] as $item): ?><li><?= $this->e((string) $item) ?></li><?php endforeach; ?></ol>
            <p class="alert alert-info">This preview has not changed the repository, database, DNS or live sites. Promotion requires a reviewed migration and configuration change.</p>
        <?php endif; ?>
    </section>
</div>
<?php $this->endSection(); ?>

