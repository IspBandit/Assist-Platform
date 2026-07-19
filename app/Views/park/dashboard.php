<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<string,bool> $checklist */
/** @var int $complete */
/** @var int $totalChecks */
/** @var array<int,array<string,mixed>> $recentRequests */
/** @var array<int,array<string,mixed>> $serviceDays */
$this->extend('layouts.public');
$statusLabels = ['draft' => 'Draft', 'pending' => 'Pending review', 'active' => 'Active', 'suspended' => 'Suspended', 'rejected' => 'Not approved'];
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Caravan park dashboard</h1>
        <?php $this->include('partials.park-nav', ['active' => 'dashboard']); ?>

        <div class="card stack">
            <div class="btn-row" style="justify-content:space-between">
                <h2 style="margin:0"><?= $this->e((string) $park['name']) ?></h2>
                <span class="badge badge-neutral"><?= $this->e($statusLabels[$park['status']] ?? (string) $park['status']) ?></span>
            </div>
            <?php if ($park['status'] === 'pending'): ?>
                <p class="muted" style="margin:0">Your application is awaiting review. You can complete your profile in the meantime.</p>
            <?php elseif ($park['status'] === 'active'): ?>
                <p class="muted" style="margin:0">Your park is active. <?php if ($park['public_page_enabled']): ?><a href="<?= e(url('caravan-parks/' . $park['slug'])) ?>" target="_blank" rel="noopener">View your public page</a>.<?php else: ?>Enable your public page from <a href="<?= e(url('park/profile')) ?>">your profile</a>.<?php endif; ?></p>
            <?php endif; ?>
        </div>

        <div class="grid grid-2" style="align-items:flex-start">
            <div class="card stack">
                <h2 style="margin-top:0">Profile checklist <span class="muted" style="font-size:1rem">(<?= $complete ?>/<?= $totalChecks ?>)</span></h2>
                <ul class="list-plain">
                    <?php foreach ($checklist as $label => $done): ?>
                        <li><?= $done ? '✅' : '⬜' ?> <?= $this->e($label) ?></li>
                    <?php endforeach; ?>
                </ul>
                <a class="btn btn-secondary" href="<?= e(url('park/profile')) ?>">Edit profile</a>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Help a guest now</h2>
                <p class="muted" style="margin:0">Register a service request for a guest, or print your QR code so guests can do it themselves.</p>
                <div class="btn-row">
                    <a class="btn btn-primary" href="<?= e(url('park/register-request')) ?>">Register guest request</a>
                    <a class="btn btn-ghost" href="<?= e(url('park/materials')) ?>">QR code &amp; materials</a>
                </div>
            </div>
        </div>

        <div class="card stack">
            <h2 style="margin-top:0">Recent guest requests</h2>
            <?php if ($recentRequests === []): ?>
                <p class="muted">No guest requests yet.</p>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="data">
                        <thead><tr><th>Reference</th><th>Summary</th><th>Status</th><th>Submitted</th></tr></thead>
                        <tbody>
                        <?php foreach ($recentRequests as $r): ?>
                            <tr>
                                <td><strong><?= $this->e((string) $r['reference']) ?></strong></td>
                                <td><?= $this->e((string) $r['title']) ?></td>
                                <td><span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span></td>
                                <td><?= $this->e((string) ($r['created_at'] ?? '')) ?></td>
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
