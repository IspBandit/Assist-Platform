<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $values */
/** @var int|null $previewCount */
/** @var string|null $formError */
/** @var array<string,string> $audiences */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
/** @var array<int,array<string,mixed>> $categories */
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
        <div class="alert alert-success">This audience currently has <strong><?= (int) $previewCount ?></strong> recipient(s). Review the message, then send or schedule.</div>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/notifications/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>

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

        <div class="form-group">
            <label for="scheduled_at">Schedule for (optional)</label>
            <input type="datetime-local" id="scheduled_at" name="scheduled_at" value="<?= e_attr((string) $v('scheduled_at')) ?>">
        </div>

        <div class="btn-row">
            <button type="submit" name="action" value="preview" class="btn btn-secondary">Preview recipients</button>
            <button type="submit" name="action" value="draft" class="btn btn-ghost">Save draft</button>
            <button type="submit" name="action" value="schedule" class="btn btn-ghost">Schedule</button>
            <button type="submit" name="action" value="send" class="btn btn-primary">Send now</button>
        </div>
    </form>
</div>
<?php $this->endSection(); ?>
