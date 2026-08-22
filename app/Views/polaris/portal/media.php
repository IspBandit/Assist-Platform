<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1>Media &amp; brochures</h1>
    <p>Uploads are stored privately and stay <strong>pending review</strong>. They are not published automatically.</p>
    <form method="post" action="<?= e(url('portal/manufacturer/media')) ?>" enctype="multipart/form-data" class="polaris-stage-panel">
        <?= csrf_field() ?>
        <label>Title <input type="text" name="title" maxlength="190"></label>
        <label>Type
            <select name="media_type">
                <?php foreach (['brochure', 'floorplan', 'logo', 'hero', 'other'] as $type): ?>
                    <option value="<?= e($type) ?>"><?= e($type) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>File (PDF or image, max 10 MB)
            <input type="file" name="media" accept=".pdf,image/jpeg,image/png,image/webp" required>
        </label>
        <button class="btn btn-primary" type="submit">Upload for review</button>
    </form>
    <h2>Uploaded</h2>
    <?php if ($media === []): ?>
        <p class="muted">No media uploaded yet.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Title</th><th>Type</th><th>Status</th><th>File</th></tr></thead>
            <tbody>
            <?php foreach ($media as $row): ?>
                <tr>
                    <td><?= $this->e($row['title']) ?></td>
                    <td><?= $this->e($row['media_type']) ?></td>
                    <td><?= $this->e($row['review_status']) ?></td>
                    <td><?= $this->e($row['original_filename']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a></p>
</div></section>
<?php $this->endSection(); ?>
