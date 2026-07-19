<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $providers */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:900px">
        <a class="muted" href="<?= e(url('account')) ?>">&laquo; My account</a>
        <h1 style="margin:.25rem 0">Saved providers</h1>

        <?php if ($providers === []): ?>
            <div class="card"><p class="mb-0">You have not saved any providers yet. Use the <strong>Save</strong> button on a provider profile to short-list them here.</p></div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($providers as $p): ?>
                    <div class="card stack">
                        <h3 style="margin:0"><a href="<?= e(url('providers/' . $p['slug'])) ?>"><?= $this->e((string) $p['business_name']) ?></a></h3>
                        <div>
                            <?= $p['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                            <span class="badge badge-neutral"><?= $this->e(ucfirst((string) $p['service_model'])) ?></span>
                        </div>
                        <form method="post" action="<?= e(url('account/providers/save')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="provider_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="action" value="unsave">
                            <button type="submit" class="btn btn-ghost btn-sm">Remove</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
