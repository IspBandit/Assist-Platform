<?php
/** @var \App\Core\View $this */
/** @var array $page */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> / <?= $this->e($page['title']) ?>
        </nav>
        <article class="card">
            <h1><?= $this->e($page['title']) ?></h1>
            <div class="stack"><?= $page['body'] /* trusted admin-managed HTML */ ?></div>
        </article>
    </div>
</section>
<?php $this->endSection(); ?>
