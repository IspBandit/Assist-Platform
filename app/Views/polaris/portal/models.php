<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <p><a href="<?= e(url('portal/manufacturer')) ?>">← Portal</a></p>
    <h1>Models for <?= $this->e($manufacturer['trading_name']) ?></h1>
    <table class="table">
        <thead><tr><th>Model</th><th>Category</th><th>Status</th><th>Verification</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($models as $model): ?>
            <tr>
                <td><?= $this->e($model['name']) ?></td>
                <td><?= $this->e($model['category']) ?></td>
                <td><?= $this->e($model['production_status']) ?></td>
                <td><?= $this->e($model['verification_status']) ?></td>
                <td><a href="<?= e(url('portal/manufacturer/models/' . $model['id'])) ?>">Edit</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div></section>
<?php $this->endSection(); ?>
