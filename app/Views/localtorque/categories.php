<?php
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,mixed>|null $category */
/** @var array<int,array<string,mixed>> $providers */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> / <a href="<?= e(url('services')) ?>">Automotive specialists</a>
            <?php if ($category !== null): ?> / <?= $this->e((string) $category['name']) ?><?php endif; ?>
        </nav>
        <?php if ($category === null): ?>
            <span class="product-kicker dark">Local automotive expertise</span>
            <h1>Browse automotive specialists</h1>
            <p class="muted">Choose the work you need, then refine the results by business name or location.</p>
            <div class="grid grid-3" style="margin-top:1.5rem">
                <?php foreach ($categories as $item): ?>
                    <article class="card">
                        <h2 style="font-size:1.2rem;margin-top:0"><a href="<?= e(url('category/' . $item['slug'])) ?>"><?= $this->e((string) $item['name']) ?></a></h2>
                        <p class="muted mb-0"><?= $this->e((string) ($item['short_description'] ?? '')) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="product-kicker dark">Automotive business directory</span>
            <h1><?= $this->e((string) $category['name']) ?></h1>
            <p><?= $this->e((string) ($category['short_description'] ?? '')) ?></p>
            <div class="btn-row" style="margin:1rem 0 1.5rem">
                <a class="btn btn-primary" href="<?= e(url('providers?category=' . (int) $category['id'])) ?>">Search and filter</a>
                <a class="btn btn-ghost" href="<?= e(url('services')) ?>">All categories</a>
            </div>
            <?php if ($providers === []): ?>
                <div class="card"><h2>Coverage is growing</h2><p class="muted">No matching published businesses are available yet. Business owners can list or claim a profile for review.</p><a class="btn btn-primary" href="<?= e(url('for-providers')) ?>">List or claim a business</a></div>
            <?php else: ?>
                <div class="grid grid-3">
                    <?php foreach ($providers as $p): ?><?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => false]); ?><?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
