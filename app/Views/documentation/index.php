<?php $this->extend($documentationLayout); ?>
<?php $this->section('content'); ?>
<section class="docs-shell">
    <div class="container">
        <header class="docs-header">
            <p class="eyebrow">Living documentation</p>
            <h1><?= $this->e($title) ?></h1>
            <p>Search the version-controlled guides for the current platform, its brands and each operating role.</p>
        </header>
        <?php $this->include('documentation._navigation'); ?>

        <form class="docs-search" method="get" action="<?= e(url(ltrim($documentationBase, '/'))) ?>" role="search">
            <label class="docs-search-query"><span>Search documentation</span><input type="search" name="q" value="<?= e_attr($filters['q'] ?? '') ?>" placeholder="Try providers, email campaigns or feature flags"></label>
            <?php foreach ([
                'audience' => ['Audience', $filterOptions['audiences']],
                'brand' => ['Brand', $filterOptions['brands']],
                'module' => ['Module', $filterOptions['modules']],
                'version' => ['Version', $filterOptions['versions']],
            ] as $filterName => [$filterLabel, $filterValues]): ?>
                <label><span><?= $this->e($filterLabel) ?></span><select name="<?= e_attr($filterName) ?>"><option value="">All</option><?php foreach ($filterValues as $filterValue): ?><option value="<?= e_attr((string) $filterValue) ?>"<?= ($filters[$filterName] ?? '') === $filterValue ? ' selected' : '' ?>><?= $this->e(ucwords(str_replace('-', ' ', (string) $filterValue))) ?></option><?php endforeach; ?></select></label>
            <?php endforeach; ?>
            <button class="btn btn-primary" type="submit">Search guides</button>
        </form>

        <div class="docs-results-heading"><h2><?= count($results) ?> matching <?= count($results) === 1 ? 'article' : 'articles' ?></h2><?php if (array_filter($filters) !== []): ?><a href="<?= e(url(ltrim($documentationBase, '/'))) ?>">Clear filters</a><?php endif; ?></div>
        <?php if ($results === []): ?>
            <div class="card empty-state"><h2>No matching documentation</h2><p>Try a broader phrase or remove one of the filters.</p></div>
        <?php else: ?>
            <div class="docs-result-grid">
                <?php foreach ($results as $result): ?>
                    <article class="card docs-result-card">
                        <div class="docs-result-meta"><span><?= $this->e(ucwords(str_replace('-', ' ', (string) $result['guide']))) ?></span><span>v<?= $this->e((string) $result['version_introduced']) ?></span></div>
                        <h3><a href="<?= e(url(ltrim($documentationBase . '/' . $result['guide'] . '/' . $result['slug'], '/'))) ?>"><?= $this->e((string) $result['title']) ?></a></h3>
                        <p><?= $this->e((string) ($result['excerpt'] ?? $result['summary'])) ?></p>
                        <small><?= $this->e(ucwords(str_replace('-', ' ', (string) $result['module']))) ?> · Updated <?= $this->e((string) $result['last_updated']) ?></small>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
