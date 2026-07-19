<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $rows */
/** @var string $range */
/** @var string $from */
/** @var string $to */
/** @var string $rangeLabel */
$this->extend('layouts.admin');
$qs = http_build_query(['range' => $range, 'from' => $from, 'to' => $to]);
?>
<?php $this->section('content'); ?>
<h1>Provider usage</h1>
<?php $this->include('partials.demand-range', ['action' => url('admin/demand/providers'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>
<div class="btn-row" style="margin:.5rem 0 1rem">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/export?type=providers&' . $qs)) ?>">Export CSV</a>
</div>

<p class="muted">"Contacts" are interest signals (clicks/requests). "Confirmed" columns come only from confirmed outcomes — never raw clicks.</p>
<div class="card" style="overflow-x:auto">
    <table class="table">
        <thead>
            <tr>
                <th>Provider</th><th>Impr.</th><th>Views</th><th>Contacts</th><th>Engagements</th>
                <th>Customer-confirmed</th><th>Provider-only</th><th>Mutual</th><th>Cancelled</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td>
                        <a href="<?= e(url('admin/providers/show?id=' . (int) $r['provider_id'])) ?>"><?= $this->e((string) $r['business_name']) ?></a>
                        <?= $r['is_verified'] ? ' <span class="badge badge-verified">✓</span>' : '' ?>
                    </td>
                    <td><?= (int) $r['impressions'] ?></td>
                    <td><?= (int) $r['profile_views'] ?></td>
                    <td><?= (int) $r['contacts'] ?></td>
                    <td><?= (int) $r['engagements'] ?></td>
                    <td><strong><?= (int) $r['customer_confirmed'] ?></strong></td>
                    <td><?= (int) $r['provider_confirmed'] ?></td>
                    <td><?= (int) $r['mutually_confirmed'] ?></td>
                    <td><?= (int) $r['cancellations'] ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($rows === []): ?><tr><td colspan="9" class="muted">No activity in this period.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php $this->endSection(); ?>
