<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $notification */
/** @var array<int,array<string,mixed>> $recipients */
/** @var array<int,array<string,mixed>> $tests */
/** @var int $previewCount */
$this->extend('layouts.admin');
$status = (string) $notification['status'];
$stage = (string) ($notification['delivery_stage'] ?? 'draft');
$canCancel = !in_array($status, ['sent', 'cancelled'], true);
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $this->e((string) $notification['title']) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/notifications')) ?>">Back</a>
    </div>
    <p class="muted">
        Status: <strong><?= $this->e($status) ?></strong> ·
        Stage: <strong><?= $this->e($stage) ?></strong> ·
        Audience: <strong><?= $this->e((string) $notification['audience_type']) ?></strong> ·
        <?php if ($status === 'sent'): ?>Sent to <strong><?= (int) $notification['recipient_count'] ?></strong> recipient(s)<?php else: ?>Estimated recipients: <strong><?= (int) $previewCount ?></strong><?php endif; ?>
    </p>

    <div style="border:1px solid #e3e0d8;border-radius:8px;padding:1rem;background:#fff;margin:1rem 0">
        <?= $notification['body'] /* trusted admin-authored HTML */ ?>
    </div>

    <?php if (!in_array($status, ['sent', 'cancelled'], true)): ?>
        <div class="card" style="margin:1rem 0">
            <h2 style="margin-top:0">Safe delivery stages</h2>
            <ol>
                <li>Send and inspect an internal test.</li>
                <li>Queue no more than 25 consent-eligible providers, then review replies, complaints, bounces and opt-outs.</li>
                <li>After review, queue no more than 50 in any rolling 24 hours.</li>
                <li>Only after another review, raise the hard cap to 100 in any rolling 24 hours.</li>
            </ol>
            <?php if (in_array($stage, ['draft', 'test'], true)): ?>
                <form method="post" action="<?= e(url('admin/notifications/test')) ?>" class="btn-row">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                    <label for="test_email" class="sr-only">Internal test email</label>
                    <input type="email" id="test_email" name="test_email" placeholder="Internal test email" required>
                    <button type="submit" class="btn btn-secondary">Queue internal test</button>
                </form>
            <?php endif; ?>
            <div class="btn-row" style="margin-top:1rem">
                <?php if ($stage === 'test'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="pilot"><button class="btn btn-primary">Test checked — queue pilot (max 25)</button></form>
                <?php elseif ($stage === 'pilot'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_50"><button class="btn btn-primary">Pilot reviewed — start 50/day</button></form>
                <?php elseif ($stage === 'daily_50'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_50"><button class="btn btn-secondary">Queue next batch (50/day cap)</button></form>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_100"><button class="btn btn-primary">50/day reviewed — raise to 100/day</button></form>
                <?php elseif ($stage === 'daily_100'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_100"><button class="btn btn-primary">Queue next batch (100/day cap)</button></form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canCancel): ?>
        <div class="btn-row">
            <form method="post" action="<?= e(url('admin/notifications/cancel')) ?>" style="margin:0">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                <button type="submit" class="btn btn-ghost">Cancel campaign and pending email</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($tests !== []): ?>
<div class="card"><h2 style="margin-top:0">Internal tests</h2><ul><?php foreach ($tests as $test): ?><li><?= $this->e((string) $test['recipient_email']) ?> — <?= $this->e((string) $test['created_at']) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

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
