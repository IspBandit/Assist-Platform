<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <h1><?= $this->e($title) ?></h1>
    <p class="badge badge-neutral">Scaffolded</p>
    <p>This surface is routed and brand-gated. Full behaviour is Planned — see <code>docs/polaris/IMPLEMENTATION_ROADMAP.md</code>.</p>
    <p><a class="btn btn-primary" href="<?= e(url('find')) ?>">Find My RV</a> <a class="btn btn-ghost" href="<?= e(url('rvs')) ?>">Browse RVs</a></p>
</div></section>
<?php $this->endSection(); ?>
