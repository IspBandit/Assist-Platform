<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $request */
/** @var array<int,array<string,mixed>> $images */
/** @var array<int,array<string,mixed>> $history */
$this->extend('layouts.public');
$r = $request;
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <a class="muted" href="<?= e(url('account/requests')) ?>">&laquo; Back to my requests</a>
        <div class="btn-row" style="justify-content:space-between;align-items:center;margin-top:.25rem">
            <h1 style="margin:0"><?= $this->e((string) $r['title']) ?></h1>
            <span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span>
        </div>
        <p class="muted">Reference <strong><?= $this->e((string) $r['reference']) ?></strong> · submitted <?= $this->e((string) $r['created_at']) ?></p>

        <div class="card stack">
            <h2 style="margin-top:0">Details</h2>
            <?php if ($r['description']): ?><p><?= nl2br($this->e((string) $r['description'])) ?></p><?php endif; ?>
            <ul class="list-plain">
                <?php if ($r['location_label']): ?><li><strong>Location:</strong> <?= $this->e((string) $r['location_label']) ?></li><?php endif; ?>
                <li><strong>Urgency:</strong> <?= $this->e(ucfirst((string) $r['urgency'])) ?></li>
                <?php if ($r['vehicle_make'] || $r['vehicle_model']): ?><li><strong>Vehicle:</strong> <?= $this->e(trim((string) $r['vehicle_make'] . ' ' . (string) $r['vehicle_model'])) ?></li><?php endif; ?>
                <?php if ($r['travel_deadline']): ?><li><strong>Leaving the area:</strong> <?= $this->e((string) $r['travel_deadline']) ?></li><?php endif; ?>
            </ul>
        </div>

        <?php if ($images !== []): ?>
            <div class="card">
                <h2 style="margin-top:0">Photos</h2>
                <div class="btn-row">
                    <?php foreach ($images as $img): ?>
                        <a href="<?= e(url('account/requests/image?id=' . (int) $img['id'])) ?>" target="_blank">
                            <img src="<?= e(url('account/requests/image?id=' . (int) $img['id'] . '&thumb=1')) ?>" alt="Request photo" style="width:120px;height:90px;object-fit:cover;border-radius:8px">
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="card stack" style="border-left:4px solid #2e7d32">
            <h2 style="margin-top:0">Did you use a provider?</h2>
            <p class="mb-0">Let us know how this request turned out so we can confirm which providers are genuinely helping travellers.</p>
            <a class="btn btn-primary" href="<?= e(url('account/requests/' . $r['reference'] . '/outcome')) ?>">Confirm the outcome</a>
        </div>

        <div class="card">
            <h2 style="margin-top:0">Progress</h2>
            <ul class="list-plain">
                <?php foreach ($history as $h): ?>
                    <li style="border-top:1px solid #e3e0d8;padding:.5rem 0">
                        <strong><?= $this->e(\App\Services\RequestWorkflow::label((string) $h['to_status'])) ?></strong>
                        <span class="muted" style="font-size:.85rem">· <?= $this->e((string) $h['created_at']) ?></span>
                        <?php if ($h['note']): ?><div class="muted"><?= $this->e((string) $h['note']) ?></div><?php endif; ?>
                    </li>
                <?php endforeach; ?>
                <?php if ($history === []): ?><li class="muted">No updates yet.</li><?php endif; ?>
            </ul>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
