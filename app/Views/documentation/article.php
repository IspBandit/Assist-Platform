<?php $this->extend($documentationLayout); ?>
<?php $this->section('content'); ?>
<section class="docs-shell"><div class="container">
    <?php $this->include('documentation._navigation'); ?>
    <p class="breadcrumbs"><a href="<?= e(url(ltrim($documentationBase, '/'))) ?>">Documentation</a> / <a href="<?= e(url(ltrim($documentationBase . '/' . $article['guide'], '/'))) ?>"><?= $this->e(ucwords(str_replace('-', ' ', (string) $article['guide']))) ?></a> / <?= $this->e((string) $article['title']) ?></p>
    <header class="docs-header docs-header--compact"><p class="eyebrow"><?= $this->e(ucwords(str_replace('-', ' ', (string) $article['module']))) ?></p><h1><?= $this->e((string) $article['title']) ?></h1><p><?= $this->e((string) $article['summary']) ?></p></header>
    <div class="docs-article-layout">
        <aside class="card docs-article-meta" aria-label="Article information">
            <dl><div><dt>Audience</dt><dd><?= $this->e(implode(', ', (array) $article['audiences'])) ?></dd></div><div><dt>Brand</dt><dd><?= $this->e(implode(', ', (array) $article['brands'])) ?></dd></div><div><dt>Version introduced</dt><dd><?= $this->e((string) $article['version_introduced']) ?></dd></div><div><dt>Last updated</dt><dd><?= $this->e((string) $article['last_updated']) ?></dd></div><div><dt>Owner</dt><dd><?= $this->e((string) $article['owner']) ?></dd></div></dl>
        </aside>
        <article class="docs-article card"><?= $article['html'] ?></article>
    </div>
</div></section>
<?php $this->endSection(); ?>
