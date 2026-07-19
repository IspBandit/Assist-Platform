<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $runs */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>My service runs</h1>
        <?php $this->include('partials.provider-nav', ['active' => 'runs']); ?>

        <div class="card">
            <div class="btn-row" style="justify-content:space-between">
                <p class="muted" style="margin:0">Plan trips that cover one or more towns. Travellers can register interest so you know there's demand before you commit.</p>
                <a class="btn btn-primary" href="<?= e(url('provider/runs/form')) ?>">New run</a>
            </div>
        </div>

        <div class="card">
            <?php if ($runs === []): ?>
                <p class="muted">You haven't created any runs yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Run</th><th>Start</th><th>Places</th><th>Public</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        <?php foreach ($runs as $r): ?>
                            <tr>
                                <td><strong><?= $this->e((string) $r['title']) ?></strong></td>
                                <td><?= $this->e((string) ($r['start_date'] ?? '—')) ?></td>
                                <td><?= (int) $r['bookings_count'] ?><?= $r['appointments_total'] ? ' / ' . (int) $r['appointments_total'] : '' ?></td>
                                <td><?= $r['is_public'] ? 'Yes' : 'No' ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e(\App\Services\RunWorkflow::label((string) $r['status'])) ?></span></td>
                                <td><a class="btn btn-ghost" href="<?= e(url('provider/runs/show?id=' . (int) $r['id'])) ?>">Manage</a></td>
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
