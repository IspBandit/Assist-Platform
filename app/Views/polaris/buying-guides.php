<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <h1>Buying guides</h1>
    <p>Practical Australian context for complex buying decisions.</p>
    <ul class="polaris-guide-list">
        <?php foreach ($guides as $guide): ?>
            <li>
                <a href="<?= e(url('buying-guides/' . $guide['slug'])) ?>"><strong><?= $this->e($guide['title']) ?></strong></a>
                <span class="muted"><?= $this->e($guide['blurb']) ?></span>
            </li>
        <?php endforeach; ?>
    </ul>
</div></section>
<?php $this->endSection(); ?>
