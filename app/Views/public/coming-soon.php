<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <div class="card text-center">
            <h1><?= $this->e($heading ?? 'Coming soon') ?></h1>
            <p class="muted">This part of VanAssist is being rolled out shortly. In the meantime you can register the help you need and we'll be in touch.</p>
            <div class="btn-row" style="justify-content:center">
                <a class="btn btn-primary" href="<?= e(url('request-assistance')) ?>">Request assistance</a>
                <a class="btn btn-secondary" href="<?= e(url('contact')) ?>">Contact us</a>
            </div>
        </div>
    </div>
</section>
<?php $this->endSection(); ?>
