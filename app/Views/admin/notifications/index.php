<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $notifications */
/** @var array<string,int> $queue */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Notifications</h1>
        <a class="btn btn-primary" href="<?= e(url('admin/notifications/compose')) ?>">New broadcast</a>
    </div>
    <div class="grid grid-3" style="margin-top:1rem">
        <div class="card" style="margin:0;text-align:center"><div class="muted">Email queue pending</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['pending'] ?? 0) ?></div></div>
        <div class="card" style="margin:0;text-align:center"><div class="muted">Sent</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['sent'] ?? 0) ?></div></div>
        <div class="card" style="margin:0;text-align:center"><div class="muted">Failed</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['failed'] ?? 0) ?></div></div>
    </div>
    <p class="muted" style="margin-top:.5rem;font-size:.85rem">Queued email is delivered by the <code>process_email_queue</code> cron task.</p>
</div>

<div class="card">
    <h2 style="margin-top:0">Recent broadcasts</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Title</th><th>Audience</th><th>Status</th><th>Recipients</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($notifications as $n): ?>
                <tr>
                    <td><strong><?= $this->e((string) $n['title']) ?></strong><br><span class="muted" style="font-size:.8rem">by <?= $this->e((string) ($n['author'] ?? 'system')) ?></span></td>
                    <td><?= $this->e((string) $n['audience_type']) ?></td>
                    <td><?= $this->e((string) $n['status']) ?></td>
                    <td><?= (int) $n['recipient_count'] ?></td>
                    <td><?= $this->e((string) ($n['scheduled_at'] ?? $n['sent_at'] ?? $n['created_at'] ?? '')) ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/notifications/show?id=' . (int) $n['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><tr><td colspan="6" class="muted">No broadcasts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
