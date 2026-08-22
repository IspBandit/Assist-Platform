<?php $this->extend($documentationLayout); ?>
<?php $this->section('content'); ?>
<section class="docs-shell"><div class="container">
    <?php $this->include('documentation._navigation'); ?>
    <header class="docs-header docs-header--compact"><p class="eyebrow">Guide</p><h1><?= $this->e((string) $guide['title']) ?></h1><p><?= $this->e((string) $guide['summary']) ?></p></header>
    <div class="docs-result-grid">
        <?php foreach ($guide['articles'] as $article): ?>
            <article class="card docs-result-card"><div class="docs-result-meta"><span><?= $this->e(ucwords(str_replace('-', ' ', (string) $article['module']))) ?></span><span>v<?= $this->e((string) $article['version_introduced']) ?></span></div><h2><a href="<?= e(url(ltrim($documentationBase . '/' . $guide['slug'] . '/' . $article['slug'], '/'))) ?>"><?= $this->e((string) $article['title']) ?></a></h2><p><?= $this->e((string) $article['summary']) ?></p><small>Updated <?= $this->e((string) $article['last_updated']) ?> · <?= $this->e((string) $article['owner']) ?></small></article>
        <?php endforeach; ?>
    </div>
</div></section>
<?php $this->endSection(); ?>
