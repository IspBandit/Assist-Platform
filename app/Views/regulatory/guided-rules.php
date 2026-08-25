<?php
/** @var \App\Core\View $this */
/** @var array<string,string>|null $selection */
/** @var array<int,array<string,mixed>> $documents */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="guided-hero">
    <div class="container guided-hero-inner">
        <span class="product-kicker">Official source navigator</span>
        <h1>Turn the rule into a clear next step.</h1>
        <p>Choose the vehicle, jurisdiction and job. We will organise the most relevant official material, practical sequence and specialist pathway without presenting advertising as authority.</p>
    </div>
</section>

<section class="section section-alt">
    <div class="container">
        <form class="card guided-form" method="get" action="<?= e(url('rules/guided')) ?>">
            <label>Registered state or territory
                <select name="jurisdiction" required><option value="">Choose jurisdiction</option><?php foreach ($jurisdictions as $code => $label): ?><option value="<?= $this->e($code) ?>" <?= ($selection['jurisdiction'] ?? '') === $code ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select>
            </label>
            <label>Vehicle or towable
                <select name="vehicle" required><option value="">Choose vehicle</option><?php foreach ($vehicles as $key => $label): ?><option value="<?= $this->e($key) ?>" <?= ($selection['vehicle'] ?? '') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select>
            </label>
            <label>What are you doing?
                <select name="intention" required><option value="">Choose the job</option><?php foreach ($intentions as $key => $label): ?><option value="<?= $this->e($key) ?>" <?= ($selection['intention'] ?? '') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select>
            </label>
            <button class="btn btn-primary" type="submit">Build my guide</button>
        </form>

        <?php if ($selection !== null): ?>
            <div class="guided-result-heading">
                <div><span class="eyebrow">Your pathway</span><h2><?= $this->e($intentions[$selection['intention']]) ?> in <?= $this->e($selection['jurisdiction']) ?></h2></div>
                <span class="badge badge-verified"><?= count($documents) ?> official source<?= count($documents) === 1 ? '' : 's' ?></span>
            </div>
            <ol class="guided-steps"><?php foreach ($steps as $index => $step): ?><li><span><?= $index + 1 ?></span><div><h3><?= $this->e($step['title']) ?></h3><p><?= $this->e($step['body']) ?></p></div></li><?php endforeach; ?></ol>

            <div class="guided-source-grid">
                <?php foreach (array_slice($documents, 0, 6) as $document): ?>
                    <article class="card guided-source"><span class="badge badge-verified">Official source</span><h3><?= $this->e((string) $document['title']) ?></h3><p><?= $this->e((string) $document['summary']) ?></p><a class="btn btn-secondary" href="<?= $this->e((string) $document['source_url']) ?>" target="_blank" rel="noopener noreferrer">Open authority source</a></article>
                <?php endforeach; ?>
            </div>
            <?php if ($documents === []): ?><div class="alert alert-info">No exact source currently passes the publication gate for that combination. Use the complete rules library or contact the relevant authority before proceeding.</div><?php endif; ?>

            <div class="guided-save card">
                <div><h2>Keep this pathway with the vehicle</h2><p>Signed-in owners can save it to My Garage, receive consented source-change alerts and continue to a relevant provider search.</p></div>
                <?php if (current_user() !== null): ?>
                    <form method="post" action="<?= e(url('account/compliance/save')) ?>"><?= csrf_field() ?><input type="hidden" name="jurisdiction" value="<?= $this->e($selection['jurisdiction']) ?>"><input type="hidden" name="vehicle" value="<?= $this->e($selection['vehicle']) ?>"><input type="hidden" name="intention" value="<?= $this->e($selection['intention']) ?>"><button class="btn btn-primary" type="submit">Save and choose alerts</button></form>
                <?php else: ?><a class="btn btn-primary" href="<?= e(url('login')) ?>">Sign in to save</a><?php endif; ?>
            </div>
            <p class="guided-limitation"><strong>Important:</strong> <?= $this->e($limitation) ?></p>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
