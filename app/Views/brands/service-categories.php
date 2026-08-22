<?php
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,mixed>|null $category */
/** @var array<int,array<string,mixed>> $providers */
/** @var array{eyebrow:string,heading:string,intro:string,index_title:string,breadcrumb:string} $directoryCopy */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <nav aria-label="Breadcrumb" class="muted" style="font-size:.9rem;margin-bottom:1rem">
            <a href="<?= e(url('/')) ?>">Home</a> / <a href="<?= e(url('services')) ?>"><?= $this->e($directoryCopy['breadcrumb']) ?></a>
            <?php if ($category !== null): ?> / <?= $this->e((string) $category['name']) ?><?php endif; ?>
        </nav>
        <?php if ($category === null): ?>
            <span class="product-kicker dark"><?= $this->e($directoryCopy['eyebrow']) ?></span>
            <h1><?= $this->e($directoryCopy['index_title']) ?></h1>
            <p class="muted"><?= $this->e($directoryCopy['intro']) ?></p>
            <p style="margin-top:1rem"><a class="btn btn-primary" href="<?= e(url('providers')) ?>">Search the directory</a></p>
            <div class="grid grid-3" style="margin-top:1.5rem">
                <?php foreach ($categories as $item): ?>
                    <article class="card">
                        <h2 style="font-size:1.2rem;margin-top:0"><a href="<?= e(url('services/' . $item['slug'])) ?>"><?= $this->e((string) $item['name']) ?></a></h2>
                        <p class="muted mb-0"><?= $this->e((string) ($item['short_description'] ?? '')) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <span class="product-kicker dark"><?= $this->e($directoryCopy['eyebrow']) ?></span>
            <h1><?= $this->e((string) $category['name']) ?></h1>
            <p><?= $this->e((string) ($category['short_description'] ?? '')) ?></p>
            <div class="btn-row" style="margin:1rem 0 1.5rem">
                <a class="btn btn-primary" href="<?= e(url('providers?category=' . (int) $category['id'])) ?>">Search with location</a>
                <a class="btn btn-ghost" href="<?= e(url('services')) ?>">All categories</a>
            </div>
            <?php if ($providers === []): ?>
                <div class="card">
                    <h2>Coverage is growing in this category</h2>
                    <p class="muted">No published businesses are listed here yet. Use the directory search with your town or postcode, or list a relevant business for review.</p>
                    <div class="btn-row">
                        <a class="btn btn-primary" href="<?= e(url('providers?category=' . (int) $category['id'])) ?>">Search directory</a>
                        <a class="btn btn-ghost" href="<?= e(url('for-providers')) ?>">List or claim a business</a>
                    </div>
                </div>
            <?php else: ?>
                <p class="muted">Showing published businesses in this category. Confirm current services and availability directly with each business.</p>
                <div class="provider-card-grid">
                    <?php foreach ($providers as $p): ?><?php $this->include('partials.provider-result-card', ['p' => $p, 'isPossible' => false]); ?><?php endforeach; ?>
                </div>
                <p style="margin-top:1.5rem"><a class="btn btn-secondary" href="<?= e(url('providers?category=' . (int) $category['id'])) ?>">Search with your location</a></p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
