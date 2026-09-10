<?php
/** @var \App\Core\View $this */
$this->extend('layouts.admin');
$qs = http_build_query(['range' => $range, 'from' => $from, 'to' => $to]);
?>
<?php $this->section('content'); ?>
<div class="admin-page-intro"><div><p class="eyebrow">Website insights</p><h1>Provider interest</h1><p class="muted">Compare result appearances, deliberate profile opens and contact actions. These figures do not claim that a job was completed.</p></div><a class="btn btn-ghost" href="<?= e(url('admin/demand')) ?>">Back to summary</a></div>
<?php $this->include('partials.demand-range', ['action' => url('admin/demand/providers'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>
<div class="btn-row"><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/demand/export?type=providers&' . $qs)) ?>">Export CSV</a></div>
<div class="card table-wrap"><table class="data">
    <thead><tr><th>Provider</th><th>Result appearances</th><th>Profile views</th><th>Contact actions</th><th>Appearance → profile</th><th>Profile → contact</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $row): ?><tr><td><a href="<?= e(url('admin/providers/show?id=' . (int) $row['provider_id'])) ?>"><?= $this->e((string) $row['label']) ?></a></td><td><?= number_format((int) $row['impressions']) ?></td><td><?= number_format((int) $row['profile_views']) ?></td><td><strong><?= number_format((int) $row['contacts']) ?></strong></td><td><?= $row['impression_to_profile_rate'] === null ? '—' : $this->e((string)$row['impression_to_profile_rate']).'%' ?></td><td><?= $row['profile_to_contact_rate'] === null ? '—' : $this->e((string)$row['profile_to_contact_rate']).'%' ?></td></tr><?php endforeach; ?>
    <?php if ($rows === []): ?><tr><td colspan="6" class="muted">No provider interest was recorded in this period.</td></tr><?php endif; ?>
    </tbody>
</table></div>
<?php $this->endSection(); ?>
