<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $notification */
/** @var array<int,array<string,mixed>> $recipients */
/** @var int $previewCount */
$this->extend('layouts.admin');
$status = (string) $notification['status'];
$canSend = in_array($status, ['draft', 'scheduled'], true);
$canCancel = !in_array($status, ['sent', 'sending', 'cancelled'], true);
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $this->e((string) $notification['title']) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/notifications')) ?>">Back</a>
    </div>
    <p class="muted">
        Status: <strong><?= $this->e($status) ?></strong> ·
        Audience: <strong><?= $this->e((string) $notification['audience_type']) ?></strong> ·
        <?php if ($status === 'sent'): ?>Sent to <strong><?= (int) $notification['recipient_count'] ?></strong> recipient(s)<?php else: ?>Estimated recipients: <strong><?= (int) $previewCount ?></strong><?php endif; ?>
    </p>

    <div style="border:1px solid #e3e0d8;border-radius:8px;padding:1rem;background:#fff;margin:1rem 0">
        <?= $notification['body'] /* trusted admin-authored HTML */ ?>
    </div>

    <?php if ($canSend || $canCancel): ?>
        <div class="btn-row">
            <?php if ($canSend): ?>
                <form method="post" action="<?= e(url('admin/notifications/send')) ?>" style="margin:0">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                    <button type="submit" class="btn btn-primary">Send now</button>
                </form>
            <?php endif; ?>
            <?php if ($canCancel): ?>
                <form method="post" action="<?= e(url('admin/notifications/cancel')) ?>" style="margin:0">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                    <button type="submit" class="btn btn-ghost">Cancel</button>
                </form>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php if ($recipients !== []): ?>
<div class="card">
    <h2 style="margin-top:0">Recipients (<?= count($recipients) ?> shown)</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Email</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recipients as $r): ?>
                <tr><td><?= $this->e((string) $r['email']) ?></td><td><?= $this->e((string) $r['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
