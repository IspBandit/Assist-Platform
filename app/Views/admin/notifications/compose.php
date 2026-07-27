<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $values */
/** @var int|null $previewCount */
/** @var string|null $formError */
/** @var array<string,string> $audiences */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,array{label:string,subject:string,body:string}> $campaignStyles */
$this->extend('layouts.admin');
$v = static fn (string $k, $d = '') => $values[$k] ?? $d;
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Compose broadcast</h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/notifications')) ?>">Back to notifications</a>
    </div>

    <?php if ($formError): ?><div class="alert alert-error"><?= $this->e($formError) ?></div><?php endif; ?>
    <?php if ($previewCount !== null && $formError === null): ?>
        <div class="alert alert-success">This audience currently has <strong><?= (int) $previewCount ?></strong> consent-eligible recipient(s). Save the draft, send an internal test, then use the staged pilot.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/notifications/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="copy_style">Relevant provider-email starter (optional)</label>
            <div class="btn-row">
                <select id="copy_style" name="copy_style"><option value="">Choose a service family</option><?php foreach ($campaignStyles as $key => $style): ?><option value="<?= e_attr($key) ?>" <?= (string) $v('copy_style') === $key ? 'selected' : '' ?>><?= $this->e($style['label']) ?></option><?php endforeach; ?></select>
                <button type="submit" name="action" value="starter" class="btn btn-secondary" formnovalidate>Apply starter</button>
            </div>
            <p class="muted">Each starter is relevant and lightly human. Verify the selected audience and every claim before saving.</p>
        </div>
        <div class="form-group"><label for="title">Title / subject</label><input type="text" id="title" name="title" value="<?= e_attr((string) $v('title')) ?>" required></div>
        <div class="form-group"><label for="body">Message (HTML allowed)</label><textarea id="body" name="body" rows="10" required><?= e((string) $v('body')) ?></textarea></div>

        <div class="form-group">
            <label for="audience_type">Audience</label>
            <select id="audience_type" name="audience_type">
                <?php foreach ($audiences as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= (string) $v('audience_type') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <p class="muted" style="margin:.25rem 0 0;font-size:.85rem">Only the location/category field matching your chosen audience is used.</p>
        </div>

        <div class="grid grid-3">
            <div class="form-group">
                <label for="town_id">Town</label>
                <select id="town_id" name="town_id">
                    <option value="">—</option>
                    <?php foreach ($towns as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) $v('town_id') === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="region_id">Region</label>
                <select id="region_id" name="region_id">
                    <option value="">—</option>
                    <?php foreach ($regions as $r): ?><option value="<?= (int) $r['id'] ?>" <?= (int) $v('region_id') === (int) $r['id'] ? 'selected' : '' ?>><?= $this->e((string) $r['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="category_id">Service category</label>
                <select id="category_id" name="category_id">
                    <option value="">—</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $v('category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="btn-row">
            <button type="submit" name="action" value="preview" class="btn btn-secondary">Preview recipients</button>
            <button type="submit" name="action" value="draft" class="btn btn-primary">Save staged campaign</button>
        </div>
        <p class="muted">Bulk “send now” is disabled. Campaigns progress through internal test → 25-provider pilot → reviewed 50/day → reviewed 100/day.</p>
    </form>
</div>
<?php $this->endSection(); ?>
