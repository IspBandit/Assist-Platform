<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $emailTemplate */
/** @var array<int,string> $placeholders */
/** @var string $previewHtml */
/** @var string $previewSubject */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $this->e((string) $emailTemplate['name']) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/email-templates')) ?>">Back to templates</a>
    </div>
    <p class="muted">Key: <code><?= $this->e((string) $emailTemplate['template_key']) ?></code></p>

    <?php if ($placeholders !== []): ?>
        <p style="margin:.5rem 0">Available placeholders:
            <?php foreach ($placeholders as $p): ?><code style="margin-right:.4rem">{{<?= $this->e($p) ?>}}</code><?php endforeach; ?>
        </p>
    <?php endif; ?>

    <form method="post" action="<?= e(url('admin/email-templates/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $emailTemplate['id'] ?>">
        <div class="form-group"><label for="name">Name</label><input type="text" id="name" name="name" value="<?= e_attr((string) $emailTemplate['name']) ?>"></div>
        <div class="form-group"><label for="subject">Subject</label><input type="text" id="subject" name="subject" value="<?= e_attr((string) $emailTemplate['subject']) ?>" required></div>
        <div class="form-group"><label for="html_body">HTML body</label><textarea id="html_body" name="html_body" rows="14" required><?= e((string) $emailTemplate['html_body']) ?></textarea></div>
        <div class="form-group"><label for="text_body">Plain-text body (optional)</label><textarea id="text_body" name="text_body" rows="6"><?= e((string) ($emailTemplate['text_body'] ?? '')) ?></textarea></div>
        <label><input type="checkbox" name="is_enabled" value="1" <?= $emailTemplate['is_enabled'] ? 'checked' : '' ?>> Enabled</label>
        <div class="btn-row"><button type="submit" class="btn btn-primary">Save template</button></div>
    </form>
</div>

<div class="card">
    <h2 style="margin-top:0">Preview (sample data)</h2>
    <p class="muted"><strong>Subject:</strong> <?= $this->e($previewSubject) ?></p>
    <div style="border:1px solid #e3e0d8;border-radius:8px;padding:1rem;background:#fff">
        <?= $previewHtml /* trusted admin template HTML rendered with sample data */ ?>
    </div>
</div>

<div class="card">
    <h2 style="margin-top:0">Send a test</h2>
    <form method="post" action="<?= e(url('admin/email-templates/test')) ?>" class="btn-row" style="align-items:end">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $emailTemplate['id'] ?>">
        <div class="form-group" style="margin:0">
            <label for="test_email">Test recipient</label>
            <input type="email" id="test_email" name="test_email" value="<?= e_attr((string) (current_user()['email'] ?? '')) ?>" placeholder="you@example.com">
        </div>
        <button type="submit" class="btn btn-secondary">Send test email now</button>
    </form>
</div>
<?php $this->endSection(); ?>
