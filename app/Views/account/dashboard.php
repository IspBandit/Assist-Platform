<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Welcome, <?= $this->e($user['name'] ?? '') ?></h1>
        <?php if (empty($user['email_verified_at'])): ?>
            <div class="alert alert-info">Please verify your email address. Check your inbox for the verification link.</div>
        <?php endif; ?>
        <div class="card">
            <div class="btn-row" style="justify-content:space-between;align-items:center">
                <h2 style="margin:0">My requests</h2>
                <a class="btn btn-primary" href="<?= e(url('request-assistance')) ?>">New request</a>
            </div>
            <?php if (empty($requests)): ?>
                <p class="muted">You haven't submitted any requests yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Reference</th><th>Summary</th><th>Town</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($requests as $r): ?>
                            <tr>
                                <td><a href="<?= e(url('account/requests/' . $r['reference'])) ?>"><?= $this->e((string) $r['reference']) ?></a></td>
                                <td><?= $this->e((string) $r['title']) ?></td>
                                <td><?= $this->e((string) ($r['town_name'] ?? '—')) ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <a href="<?= e(url('account/requests')) ?>">View all requests</a>
            <?php endif; ?>
        </div>
        <div class="grid grid-3">
            <div class="card"><h3>Joined runs</h3><p class="muted">Service runs you have joined will appear here.</p></div>
            <div class="card"><h3>Profile & security</h3><p class="muted">Manage your details, communication preferences and password.</p></div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
