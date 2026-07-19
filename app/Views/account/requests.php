<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $requests */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <div class="btn-row" style="justify-content:space-between;align-items:center">
            <h1 style="margin:0">My requests</h1>
            <a class="btn btn-primary" href="<?= e(url('request-assistance')) ?>">New request</a>
        </div>

        <div class="card">
            <?php if ($requests === []): ?>
                <p class="muted">You haven't submitted any requests yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Reference</th><th>Summary</th><th>Town</th><th>Urgency</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><a href="<?= e(url('account/requests/' . $r['reference'])) ?>"><?= $this->e((string) $r['reference']) ?></a></td>
                                <td><?= $this->e((string) $r['title']) ?></td>
                                <td><?= $this->e((string) ($r['town_name'] ?? '—')) ?></td>
                                <td><?= $this->e(ucfirst((string) $r['urgency'])) ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span></td>
                                <td class="muted"><?= $this->e((string) $r['created_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
