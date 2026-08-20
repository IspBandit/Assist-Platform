<?php $this->extend($documentationLayout); ?>
<?php $this->section('content'); ?>
<section class="docs-shell"><div class="container">
    <?php $this->include('documentation._navigation'); ?>
    <header class="docs-header docs-header--compact"><p class="eyebrow">By release</p><h1>What's new</h1><p>Release notes and documented platform changes, newest first.</p></header>
    <div class="docs-timeline">
        <?php foreach ($articles as $article): ?><article class="card"><span class="status status-confirmed">v<?= $this->e((string) $article['version_introduced']) ?></span><h2><a href="<?= e(url(ltrim($documentationBase . '/' . $article['guide'] . '/' . $article['slug'], '/'))) ?>"><?= $this->e((string) $article['title']) ?></a></h2><p><?= $this->e((string) $article['summary']) ?></p><small>Updated <?= $this->e((string) $article['last_updated']) ?></small></article><?php endforeach; ?>
    </div>
</div></section>
<?php $this->endSection(); ?>
