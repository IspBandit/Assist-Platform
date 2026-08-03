<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <p><a href="<?= e(url('buying-guides')) ?>">Buying guides</a></p>
    <h1><?= $this->e($guideTitle) ?></h1>
    <p class="badge badge-neutral">Scaffolded guide</p>
    <p>Full editorial content for <strong><?= $this->e($guideTitle) ?></strong> is Planned. This route exists so information architecture and SEO structure can be validated early.</p>
    <p>Polaris explains values in Australian terms (ATM, GTM, GVM, GCM, AUD, metric) and never presents incomplete data as certainty.</p>
    <a class="btn btn-primary" href="<?= e(url('find')) ?>">Find My RV</a>
</div></section>
<?php $this->endSection(); ?>
