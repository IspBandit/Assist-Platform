<?php
/** @var \App\Core\View $this */
/** @var array<string,int> $overview */
/** @var array<int,array{name:string,c:int}> $byCategory */
/** @var array<int,array{name:string,c:int}> $byUrgency */
/** @var array<int,array{name:string,c:int}> $byState */
/** @var array<int,array{name:string,c:int}> $byTown */
/** @var array<int,array{name:string,c:int}> $byVehicle */
/** @var string $range */
/** @var string $from */
/** @var string $to */
/** @var string $rangeLabel */
$this->extend('layouts.admin');
$qs = http_build_query(['range' => $range, 'from' => $from, 'to' => $to]);
$stat = static function (string $label, $value, string $hint = '') {
    echo '<div class="card stack" style="gap:.25rem"><div style="font-size:1.5rem;font-weight:700">' . e((string) $value) . '</div>'
        . '<div class="muted">' . e($label) . '</div>' . ($hint !== '' ? '<div class="muted" style="font-size:.8rem">' . e($hint) . '</div>' : '') . '</div>';
};
$list = function (array $rows, string $title) {
    echo '<div class="card"><h3 style="margin-top:0">' . e($title) . '</h3><ul class="list-plain">';
    foreach ($rows as $r) {
        echo '<li>' . e((string) ($r['name'] ?: '—')) . ' <span class="muted">(' . (int) $r['c'] . ')</span></li>';
    }
    if ($rows === []) {
        echo '<li class="muted">No data yet.</li>';
    }
    echo '</ul></div>';
};
?>
<?php $this->section('content'); ?>
<h1>Demand analytics</h1>
<?php $this->include('partials.demand-range', ['action' => url('admin/demand'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>

<div class="btn-row" style="margin:.5rem 0 1rem">
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/export?type=overview&' . $qs)) ?>">Export overview</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/export?type=demand_category&' . $qs)) ?>">Export by category</a>
    <a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/export?type=demand_town&' . $qs)) ?>">Export by town</a>
</div>

<div class="grid grid-4">
    <?php
    $stat('Needs submitted', $overview['needs']);
    $stat('Searches', $overview['searches']);
    $stat('No-result searches', $overview['no_result']);
    $stat('Provider impressions', $overview['impressions']);
    $stat('Profile views', $overview['profile_views']);
    $stat('Contact actions', $overview['contacts']);
    $stat('Confirmed use (customer)', $overview['customer_confirmed'], 'customer/admin confirmed');
    $stat('Mutually confirmed jobs', $overview['mutually_confirmed']);
    ?>
</div>

<div class="grid grid-3" style="margin-top:1rem">
    <?php
    $list($byCategory, 'Top categories');
    $list($byTown, 'Top towns');
    $list($byState, 'By state');
    $list($byUrgency, 'By urgency');
    $list($byVehicle, 'By vehicle type');
    ?>
</div>
<?php $this->endSection(); ?>
