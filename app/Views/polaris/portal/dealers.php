<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p class="eyebrow">Manufacturer portal</p>
    <h1>Dealer relationships</h1>
    <p>Link authorised dealers that represent your brand. This is not a used-stock inventory marketplace.</p>
    <form method="get" action="<?= e(url('portal/manufacturer/dealers')) ?>" class="filter-inline">
        <label>Search dealers <input type="search" name="q" value="<?= $this->e($query) ?>"></label>
        <button class="btn btn-secondary btn-sm" type="submit">Search</button>
    </form>
    <h2>Linked</h2>
    <?php if ($linked === []): ?>
        <p class="muted">No dealers linked yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($linked as $dealer): ?>
                <li><?= $this->e($dealer['trading_name']) ?> — <?= $this->e((string) ($dealer['locality'] ?? '')) ?> <?= $this->e((string) ($dealer['state_abbr'] ?? '')) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <h2>Available dealers</h2>
    <?php if ($candidates === []): ?>
        <p class="muted">No matching dealers. Ask admin to add a dealer profile first.</p>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>Dealer</th><th>Location</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($candidates as $dealer): ?>
                <tr>
                    <td><?= $this->e($dealer['trading_name']) ?></td>
                    <td><?= $this->e(trim(($dealer['locality'] ?? '') . ' ' . ($dealer['state_abbr'] ?? ''))) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('portal/manufacturer/dealers/link')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="dealer_id" value="<?= (int) $dealer['id'] ?>">
                            <button class="btn btn-secondary btn-sm" type="submit">Link</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
    <p><a class="btn btn-ghost" href="<?= e(url('portal/manufacturer')) ?>">Portal home</a></p>
</div></section>
<?php $this->endSection(); ?>
