<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $coverage */
/** @var string $range */
/** @var string $from */
/** @var string $to */
/** @var string $rangeLabel */
$this->extend('layouts.admin');
$reasonLabels = [
    'none_nearby' => 'No provider nearby', 'none_soon_enough' => 'None soon enough', 'no_mobile' => 'No mobile',
    'no_workshop' => 'No workshop', 'outside_area' => 'Outside area', 'wrong_category' => 'Wrong category',
    'could_not_assist' => 'Could not assist', 'price' => 'Price', 'no_contact' => 'Could not contact',
    'no_response' => 'No response', 'licensing' => 'Licensing concerns', 'found_elsewhere' => 'Found elsewhere', 'other' => 'Other',
];
?>
<?php $this->section('content'); ?>
<h1>Coverage gaps</h1>
<?php $this->include('partials.demand-range', ['action' => url('admin/demand/coverage'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>

<div class="grid grid-2">
    <div class="card">
        <h3 style="margin-top:0">Searches returning zero results</h3>
        <table class="table">
            <thead><tr><th>Town</th><th>Category</th><th>Count</th></tr></thead>
            <tbody>
                <?php foreach ($coverage['zero_result'] as $r): ?>
                    <tr><td><?= $this->e((string) $r['town']) ?></td><td><?= $this->e((string) $r['category']) ?></td><td><?= (int) $r['c'] ?></td></tr>
                <?php endforeach; ?>
                <?php if ($coverage['zero_result'] === []): ?><tr><td colspan="3" class="muted">None.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0">"Couldn't find" feedback by reason</h3>
        <ul class="list-plain">
            <?php foreach ($coverage['gap_reasons'] as $r): ?>
                <li><?= e($reasonLabels[$r['reason']] ?? (string) $r['reason']) ?> <span class="muted">(<?= (int) $r['c'] ?>)</span></li>
            <?php endforeach; ?>
            <?php if ($coverage['gap_reasons'] === []): ?><li class="muted">None.</li><?php endif; ?>
        </ul>
        <h4>By town</h4>
        <ul class="list-plain">
            <?php foreach ($coverage['gap_towns'] as $r): ?><li><?= $this->e((string) $r['name']) ?> <span class="muted">(<?= (int) $r['c'] ?>)</span></li><?php endforeach; ?>
            <?php if ($coverage['gap_towns'] === []): ?><li class="muted">None.</li><?php endif; ?>
        </ul>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Shown often, rarely contacted</h3>
        <table class="table">
            <thead><tr><th>Provider</th><th>Impressions</th><th>Contacts</th></tr></thead>
            <tbody>
                <?php foreach ($coverage['shown_not_contacted'] as $r): ?>
                    <tr><td><?= $this->e((string) $r['business_name']) ?></td><td><?= (int) $r['impressions'] ?></td><td><?= (int) $r['contacts'] ?></td></tr>
                <?php endforeach; ?>
                <?php if ($coverage['shown_not_contacted'] === []): ?><tr><td colspan="3" class="muted">None.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3 style="margin-top:0">Contacted, not yet confirmed as used</h3>
        <table class="table">
            <thead><tr><th>Provider</th><th>Contacts</th><th>Confirmed</th></tr></thead>
            <tbody>
                <?php foreach ($coverage['contacted_not_confirmed'] as $r): ?>
                    <tr><td><?= $this->e((string) $r['business_name']) ?></td><td><?= (int) $r['contacts'] ?></td><td><?= (int) $r['customer_confirmed'] ?></td></tr>
                <?php endforeach; ?>
                <?php if ($coverage['contacted_not_confirmed'] === []): ?><tr><td colspan="3" class="muted">None.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
