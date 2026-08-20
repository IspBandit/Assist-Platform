<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1>Polaris recycle bin</h1>
    <p>Soft-deleted / recycle-bin records. Permanent purge remains reserved for authorised OPS-011 workflows.</p>
    <h2>Models</h2>
    <ul><?php foreach ($models as $row): ?><li><?= $this->e($row['manufacturer_name'] . ' — ' . $row['name']) ?></li><?php endforeach; ?></ul>
    <?php if ($models === []): ?><p class="muted">No models in recycle bin.</p><?php endif; ?>
    <h2>Manufacturers</h2>
    <ul><?php foreach ($manufacturers as $row): ?><li><?= $this->e($row['trading_name']) ?></li><?php endforeach; ?></ul>
    <?php if ($manufacturers === []): ?><p class="muted">No manufacturers in recycle bin.</p><?php endif; ?>
</div>
<?php $this->endSection(); ?>
