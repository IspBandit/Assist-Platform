<?php
/** @var \App\Core\View $this */
/** @var array<int,array{label:string,count:int,rate:?float}> $funnel */
/** @var string $range */
/** @var string $from */
/** @var string $to */
/** @var string $rangeLabel */
$this->extend('layouts.admin');
$qs = http_build_query(['range' => $range, 'from' => $from, 'to' => $to]);
$top = $funnel[0]['count'] ?? 0;
?>
<?php $this->section('content'); ?>
<h1>Conversion funnel</h1>
<?php $this->include('partials.demand-range', ['action' => url('admin/demand/funnel'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>
<div class="btn-row" style="margin:.5rem 0 1rem">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/export?type=funnel&' . $qs)) ?>">Export CSV</a>
</div>

<div class="card stack">
    <?php foreach ($funnel as $stage): ?>
        <?php $width = $top > 0 ? max(2, (int) round($stage['count'] / $top * 100)) : 0; ?>
        <div>
            <div class="btn-row" style="justify-content:space-between;margin:0">
                <strong><?= $this->e($stage['label']) ?></strong>
                <span class="muted">
                    <?= (int) $stage['count'] ?>
                    <?php if ($stage['rate'] !== null): ?> · <?= e((string) $stage['rate']) ?>% from previous<?php endif; ?>
                </span>
            </div>
            <div style="background:#e3e0d8;border-radius:6px;height:16px;margin-top:.25rem">
                <div style="background:#2e7d32;height:16px;border-radius:6px;width:<?= $width ?>%"></div>
            </div>
        </div>
    <?php endforeach; ?>
</div>
<p class="muted" style="font-size:.85rem">Conversion percentages are hidden where the previous stage has fewer than 5 events.</p>
<?php $this->endSection(); ?>
