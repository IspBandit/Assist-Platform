<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="admin-page">
    <h1><?= $this->e($title) ?></h1>
    <p class="badge badge-neutral">Scaffolded admin section</p>
    <p>Route <code>/admin/polaris/<?= e($section) ?></code> is reserved. Implementation follows <code>docs/polaris/ADMINISTRATION.md</code>.</p>
    <p><a href="<?= e(url('admin/polaris')) ?>">Back to Polaris overview</a></p>
</div>
<?php $this->endSection(); ?>
