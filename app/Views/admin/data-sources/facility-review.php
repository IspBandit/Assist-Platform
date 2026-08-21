<?php
/** @var \App\Core\View $this */
/** @var list<array<string,mixed>> $candidates */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="page-header">
    <div>
        <p class="eyebrow">Data Sources</p>
        <h1>Traveller facility import review</h1>
        <p class="muted">Approve to publish into <code>traveller_facilities</code> as active + reviewed. Never invents park rows.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/data-sources/datasets')) ?>">Datasets</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/data-sources/review')) ?>">Provider import review</a>
    </div>
</div>

<?php if ($candidates === []): ?>
    <section class="card"><p class="muted">No pending facility candidates.</p></section>
<?php else: ?>
<form method="post" action="<?= e(url('admin/data-sources/facilities/review')) ?>" id="facility-bulk-form" class="card" style="margin-bottom:0.75rem">
    <?= csrf_field() ?>
    <div class="btn-row">
        <input type="text" name="notes" placeholder="Bulk review notes" style="max-width:20rem">
        <button class="btn btn-primary btn-sm" name="action" value="approve" type="submit">Approve selected</button>
        <button class="btn btn-ghost btn-sm" name="action" value="reject" type="submit">Reject selected</button>
    </div>
</form>

<?php foreach ($candidates as $candidate): ?>
    <article class="card" style="margin-bottom:0.75rem">
        <label style="display:flex;gap:0.75rem;align-items:flex-start">
            <input type="checkbox" name="candidate_ids[]" value="<?= (int) $candidate['id'] ?>" form="facility-bulk-form">
            <span style="flex:1">
                <h2 class="h3" style="margin:0 0 0.35rem"><?= $this->e((string) $candidate['name']) ?></h2>
                <p class="muted" style="margin:0">
                    <?= $this->e((string) $candidate['facility_type']) ?>
                    <?php if (!empty($candidate['dataset_title'])): ?> · <?= $this->e((string) $candidate['dataset_title']) ?><?php endif; ?>
                    <?php if (!empty($candidate['locality'])): ?> · <?= $this->e((string) $candidate['locality']) ?><?php endif; ?>
                    <?php if (!empty($candidate['formatted_address'])): ?> · <?= $this->e((string) $candidate['formatted_address']) ?><?php endif; ?>
                    <?php if ($candidate['latitude'] !== null && $candidate['longitude'] !== null): ?>
                        · <?= $this->e((string) $candidate['latitude']) ?>, <?= $this->e((string) $candidate['longitude']) ?>
                    <?php endif; ?>
                </p>
                <?php if (!empty($candidate['duplicate_facility_id'])): ?>
                    <p class="muted">Possible duplicate facility #<?= (int) $candidate['duplicate_facility_id'] ?></p>
                <?php endif; ?>
            </span>
        </label>
        <form method="post" action="<?= e(url('admin/data-sources/facilities/review')) ?>" class="inline-form" style="margin-top:0.75rem;margin-left:1.75rem">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $candidate['id'] ?>">
            <input type="text" name="notes" placeholder="Review notes" style="max-width:20rem">
            <button class="btn btn-primary btn-sm" name="action" value="approve" type="submit">Approve &amp; publish</button>
            <button class="btn btn-ghost btn-sm" name="action" value="reject" type="submit">Reject</button>
        </form>
    </article>
<?php endforeach; ?>
<?php endif; ?>
<?php $this->endSection(); ?>
