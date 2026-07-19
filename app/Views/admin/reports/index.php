<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $funnel */
/** @var array<int,array<string,mixed>> $demandTowns */
/** @var array<int,array<string,mixed>> $demandCats */
/** @var array<int,array<string,mixed>> $providers */
/** @var array<int,array<string,mixed>> $runs */
/** @var array<int,array<string,mixed>> $parks */
/** @var array<string,int> $email */
/** @var array<int,array<string,mixed>> $traffic */
/** @var bool $analyticsOn */
$this->extend('layouts.admin');
$exportUrl = static fn (string $r) => url('admin/reports/export?report=' . $r);
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0">Reports</h1>
    <p class="muted">Live operational figures (demo records excluded). Use the export buttons for CSV downloads.</p>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="btn-row" style="justify-content:space-between">
            <h2 style="margin:0">Request funnel</h2>
            <a class="btn btn-ghost" href="<?= e($exportUrl('funnel')) ?>">CSV</a>
        </div>
        <table class="data">
            <thead><tr><th>Stage</th><th>Requests</th></tr></thead>
            <tbody>
            <?php foreach ($funnel as $f): ?>
                <tr><td><?= $this->e((string) $f['stage']) ?></td><td><strong><?= (int) $f['count'] ?></strong></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2 style="margin-top:0">Email queue</h2>
        <table class="data">
            <tbody>
                <tr><td>Pending</td><td><strong><?= (int) ($email['pending'] ?? 0) ?></strong></td></tr>
                <tr><td>Sent</td><td><strong><?= (int) ($email['sent'] ?? 0) ?></strong></td></tr>
                <tr><td>Failed</td><td><strong><?= (int) ($email['failed'] ?? 0) ?></strong></td></tr>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h2 style="margin:0">Demand by town</h2>
        <a class="btn btn-ghost" href="<?= e($exportUrl('demand_towns')) ?>">CSV</a>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Town</th><th>Region</th><th>Total</th><th>Open</th><th>Completed</th></tr></thead>
            <tbody>
            <?php foreach ($demandTowns as $d): ?>
                <tr><td><?= $this->e((string) $d['town']) ?></td><td><?= $this->e((string) ($d['region'] ?? '')) ?></td><td><?= (int) $d['total'] ?></td><td><?= (int) $d['open_count'] ?></td><td><?= (int) $d['completed'] ?></td></tr>
            <?php endforeach; ?>
            <?php if ($demandTowns === []): ?><tr><td colspan="5" class="muted">No request data yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h2 style="margin:0">Demand by category</h2>
        <a class="btn btn-ghost" href="<?= e($exportUrl('demand_categories')) ?>">CSV</a>
    </div>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Category</th><th>Total</th><th>Open</th><th>Completed</th></tr></thead>
            <tbody>
            <?php foreach ($demandCats as $d): ?>
                <tr><td><?= $this->e((string) $d['category']) ?></td><td><?= (int) $d['total'] ?></td><td><?= (int) $d['open_count'] ?></td><td><?= (int) $d['completed'] ?></td></tr>
            <?php endforeach; ?>
            <?php if ($demandCats === []): ?><tr><td colspan="4" class="muted">No request data yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-2">
    <div class="card">
        <div class="btn-row" style="justify-content:space-between">
            <h2 style="margin:0">Providers</h2>
            <a class="btn btn-ghost" href="<?= e($exportUrl('providers')) ?>">CSV</a>
        </div>
        <table class="data">
            <thead><tr><th>Status</th><th>Total</th><th>Verified</th></tr></thead>
            <tbody>
            <?php foreach ($providers as $p): ?>
                <tr><td><?= $this->e((string) $p['status']) ?></td><td><?= (int) $p['total'] ?></td><td><?= (int) $p['verified'] ?></td></tr>
            <?php endforeach; ?>
            <?php if ($providers === []): ?><tr><td colspan="3" class="muted">No providers.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="btn-row" style="justify-content:space-between">
            <h2 style="margin:0">Service runs</h2>
            <a class="btn btn-ghost" href="<?= e($exportUrl('runs')) ?>">CSV</a>
        </div>
        <table class="data">
            <thead><tr><th>Status</th><th>Total</th><th>Booked</th></tr></thead>
            <tbody>
            <?php foreach ($runs as $r): ?>
                <tr><td><?= $this->e((string) $r['status']) ?></td><td><?= (int) $r['total'] ?></td><td><?= (int) $r['booked'] ?></td></tr>
            <?php endforeach; ?>
            <?php if ($runs === []): ?><tr><td colspan="3" class="muted">No runs.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h2 style="margin:0">Traffic — top pages (30 days)</h2>
        <a class="btn btn-ghost" href="<?= e(url('admin/reports/export?report=requests')) ?>">Export requests CSV</a>
    </div>
    <?php if (!$analyticsOn): ?>
        <p class="muted">First-party analytics is currently <strong>off</strong>. Enable it in <a href="<?= e(url('admin/settings')) ?>">Settings</a> to start recording page views.</p>
    <?php endif; ?>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Route</th><th>Views</th></tr></thead>
            <tbody>
            <?php foreach ($traffic as $t): ?>
                <tr><td><?= $this->e((string) $t['route']) ?></td><td><?= (int) $t['views'] ?></td></tr>
            <?php endforeach; ?>
            <?php if ($traffic === []): ?><tr><td colspan="2" class="muted">No page views recorded.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
