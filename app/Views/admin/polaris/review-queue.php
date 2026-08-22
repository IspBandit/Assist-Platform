<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1>Polaris review queue</h1>

    <h2>Import drafts</h2>
    <?php if ($drafts === []): ?><p class="muted">No pending drafts.</p><?php endif; ?>
    <?php foreach ($drafts as $draft): ?>
        <?php $payload = json_decode((string) $draft['payload_json'], true) ?: []; ?>
        <article class="polaris-stage-panel">
            <h3><?= $this->e(($payload['manufacturer_name'] ?? '') . ' ' . ($payload['model_name'] ?? '') . ' / ' . ($payload['variant_name'] ?? '')) ?></h3>
            <p class="muted">Confidence <?= $draft['confidence'] !== null ? (int) $draft['confidence'] : '—' ?> · Job #<?= (int) $draft['job_id'] ?> · <?= $this->e((string) ($draft['original_filename'] ?? '')) ?></p>
            <pre><?= $this->e(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '') ?></pre>
            <form method="post" action="<?= e(url('admin/polaris/review-queue/draft')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $draft['id'] ?>">
                <input type="text" name="notes" placeholder="Review notes">
                <button class="btn btn-primary btn-sm" name="action" value="approve" type="submit">Approve &amp; publish</button>
                <button class="btn btn-ghost btn-sm" name="action" value="reject" type="submit">Reject</button>
            </form>
        </article>
    <?php endforeach; ?>

    <h2>Manufacturer claims</h2>
    <?php if ($claims === []): ?><p class="muted">No pending claims.</p><?php endif; ?>
    <?php foreach ($claims as $claim): ?>
        <article class="polaris-stage-panel">
            <h3><?= $this->e($claim['trading_name']) ?></h3>
            <p><?= $this->e($claim['user_name']) ?> · <?= $this->e($claim['user_email']) ?> · <?= $this->e((string) ($claim['contact_email'] ?? '')) ?></p>
            <p><?= $this->e((string) ($claim['authority_evidence'] ?? '')) ?></p>
            <form method="post" action="<?= e(url('admin/polaris/review-queue/claim')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $claim['id'] ?>">
                <input type="text" name="notes" placeholder="Review notes">
                <button class="btn btn-primary btn-sm" name="action" value="approve" type="submit">Approve claim</button>
                <button class="btn btn-ghost btn-sm" name="action" value="reject" type="submit">Reject</button>
            </form>
        </article>
    <?php endforeach; ?>

    <h2>Dealer claims</h2>
    <?php $dealerClaims = $dealerClaims ?? []; ?>
    <?php if ($dealerClaims === []): ?><p class="muted">No pending dealer claims.</p><?php endif; ?>
    <?php foreach ($dealerClaims as $claim): ?>
        <article class="polaris-stage-panel">
            <h3><?= $this->e($claim['trading_name']) ?></h3>
            <p><?= $this->e((string) ($claim['claimant_email'] ?? '—')) ?> · <?= $this->e(trim(($claim['locality'] ?? '') . ' ' . ($claim['state_abbr'] ?? ''))) ?></p>
            <form method="post" action="<?= e(url('admin/polaris/review-queue/dealer')) ?>" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="dealer_id" value="<?= (int) $claim['id'] ?>">
                <button class="btn btn-primary btn-sm" type="submit">Approve dealer claim</button>
            </form>
        </article>
    <?php endforeach; ?>
</div>
<?php $this->endSection(); ?>
