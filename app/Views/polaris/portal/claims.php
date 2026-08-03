<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <h1>Claim a manufacturer profile</h1>
    <p><strong>Search existing profiles first.</strong> Creating a duplicate is discouraged — claim or request a merge instead.</p>
    <form method="get" action="<?= e(url('portal/manufacturer/claims')) ?>" class="polaris-filter-bar">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="Manufacturer trading name" required>
        <button class="btn btn-primary" type="submit">Search</button>
    </form>
    <?php if ($duplicates !== []): ?>
        <div class="alert alert-info">
            <p>Possible matches (claim one of these rather than creating a new profile):</p>
            <ul><?php foreach ($duplicates as $dup): ?><li><?= $this->e($dup['name']) ?> — <?= $this->e((string) $dup['score']) ?>% similar</li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>
    <?php if ($matches === []): ?>
        <p class="empty-state">No manufacturers matched. Ask an administrator if you need a new profile created after searching thoroughly.</p>
    <?php else: ?>
        <ul class="polaris-match-list">
            <?php foreach ($matches as $mfr): ?>
                <li class="polaris-match-card">
                    <div>
                        <h2><?= $this->e($mfr['trading_name']) ?></h2>
                        <p class="muted"><?= $this->e($mfr['claim_status']) ?> · <?= $this->e($mfr['verification_status']) ?><?= !empty($mfr['is_demo']) ? ' · demo fixture' : '' ?></p>
                        <?php if ($mfr['claim_status'] === 'unclaimed'): ?>
                            <form method="post" action="<?= e(url('portal/manufacturer/claims')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="manufacturer_id" value="<?= (int) $mfr['id'] ?>">
                                <label>Work email <input type="email" name="contact_email" required value="<?= e((string) (current_user()['email'] ?? '')) ?>"></label>
                                <label>Authority evidence <textarea name="evidence" rows="3" required placeholder="ABN, role, manufacturer letterhead, or website contact page"></textarea></label>
                                <button class="btn btn-secondary" type="submit">Submit claim</button>
                            </form>
                        <?php else: ?>
                            <p class="muted">Not available to claim.</p>
                        <?php endif; ?>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
