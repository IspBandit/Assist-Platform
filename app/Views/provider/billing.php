<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $provider */
/** @var array<string,mixed>|null $plan */
/** @var array<string,array{cap:?int,used:int}> $limitRows */
/** @var array<int,array<string,mixed>> $availablePlans */
/** @var array<int,array<string,mixed>> $invoices */
$this->extend('layouts.public');
$money = static fn (int $cents): string => '$' . number_format($cents / 100, 2);
$label = static fn (string $key): string => ucwords(str_replace(['maximum_', '_'], ['', ' '], $key));
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Billing</h1>

        <div class="card">
            <h2>Current plan</h2>
            <p><strong><?= $this->e($plan['public_name'] ?? 'No plan assigned') ?></strong>
               &mdash; subscription state: <strong><?= $this->e((string) ($provider['subscription_state'] ?? 'complimentary')) ?></strong></p>
            <?php if (!empty($provider['is_founding_provider'])): ?>
                <p><span class="badge badge-success">Founding Provider</span> Your founding benefits are protected and will not change.</p>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2>Usage against limits</h2>
            <table class="table">
                <thead><tr><th>Limit</th><th>Used</th><th>Allowance</th></tr></thead>
                <tbody>
                <?php foreach ($limitRows as $key => $row): ?>
                    <tr>
                        <td><?= $this->e($label($key)) ?></td>
                        <td><?= (int) $row['used'] ?></td>
                        <td><?= $row['cap'] === null ? 'Unlimited' : (int) $row['cap'] ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h2>Available plans</h2>
            <div class="grid grid-3">
            <?php foreach ($availablePlans as $p): ?>
                <div class="card">
                    <h3><?= $this->e((string) $p['public_name']) ?> <?= $p['is_recommended'] ? '<span class="badge badge-success">Recommended</span>' : '' ?></h3>
                    <p><?= $money((int) $p['monthly_price_cents']) ?>/mo &middot; <?= $money((int) $p['annual_price_cents']) ?>/yr</p>
                </div>
            <?php endforeach; ?>
            </div>
            <p class="muted">Upgrade, downgrade, payment details and tax-invoice downloads are managed here once activated.</p>
        </div>

        <div class="card">
            <h2>Invoices</h2>
            <?php if ($invoices === []): ?>
                <p class="muted">No invoices yet.</p>
            <?php else: ?>
                <table class="table">
                    <thead><tr><th>Number</th><th>Date</th><th>Total</th><th>Status</th></tr></thead>
                    <tbody>
                    <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td><?= $this->e((string) $inv['invoice_number']) ?></td>
                            <td><?= $this->e((string) $inv['invoice_date']) ?></td>
                            <td><?= $money((int) $inv['total_cents']) ?></td>
                            <td><?= $this->e((string) $inv['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
