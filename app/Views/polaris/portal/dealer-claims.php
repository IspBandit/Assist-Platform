<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Dealer portal</p>
    <h1>Claim dealer profile</h1>
    <p class="muted">Claim-first for authorised dealers. Polaris does not list used stock or dealer inventory.</p>
    <form method="get" action="<?= e(url('portal/dealer/claims')) ?>" class="filter-inline">
        <label>Search <input type="search" name="q" value="<?= $this->e($query) ?>" placeholder="Dealer name or locality"></label>
        <button class="btn btn-secondary btn-sm" type="submit">Search</button>
    </form>
    <?php if ($matches === []): ?>
        <p class="muted">No dealers found. Prefer asking platform admin to create the dealer profile rather than inventing a duplicate.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Dealer</th><th>Location</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($matches as $dealer): ?>
                <tr>
                    <td><?= $this->e($dealer['trading_name']) ?></td>
                    <td><?= $this->e(trim(($dealer['locality'] ?? '') . ' ' . ($dealer['state_abbr'] ?? ''))) ?></td>
                    <td><?= $this->e($dealer['claim_status']) ?></td>
                    <td>
                        <?php if ($dealer['claim_status'] === 'unclaimed'): ?>
                            <form method="post" action="<?= e(url('portal/dealer/claims')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="dealer_id" value="<?= (int) $dealer['id'] ?>">
                                <label>Evidence <input type="text" name="evidence" required placeholder="Why you can claim this profile"></label>
                                <button class="btn btn-primary btn-sm" type="submit">Submit claim</button>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
