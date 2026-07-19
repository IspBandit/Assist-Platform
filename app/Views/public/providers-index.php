<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $providers */
/** @var int $total */
/** @var int $page */
/** @var int $perPage */
/** @var string $search */
/** @var int|null $townId */
/** @var int|null $categoryId */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.public');
$pages = (int) ceil(max(1, $total) / $perPage);
$qs = static function (array $extra) use ($search, $townId, $categoryId): string {
    $params = array_filter(['q' => $search, 'town' => $townId, 'category' => $categoryId] + $extra, static fn ($v) => $v !== null && $v !== '');
    return $params === [] ? '' : ('?' . http_build_query($params));
};
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Find a service provider</h1>
        <p class="muted">Browse caravan and RV service providers in the VanAssist network. Verified providers display a verification badge.</p>

        <form method="get" action="<?= e(url('providers')) ?>" class="grid grid-3" style="margin:1.5rem 0;align-items:flex-end">
            <div class="form-group mb-0">
                <label for="q">Search</label>
                <input type="text" id="q" name="q" value="<?= e($search) ?>" placeholder="Business name">
            </div>
            <div class="form-group mb-0">
                <label for="town">Town</label>
                <select id="town" name="town">
                    <option value="">All towns</option>
                    <?php foreach ($towns as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= $townId === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0">
                <label for="category">Service</label>
                <select id="category" name="category">
                    <option value="">All services</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0"><button type="submit" class="btn btn-primary">Search</button></div>
        </form>

        <?php if ($providers === []): ?>
            <div class="card text-center">
                <p class="muted">No providers match your search yet. Try widening your filters, or
                    <a href="<?= e(url('request-assistance')) ?>">register a request</a> to attract providers to your area.</p>
            </div>
        <?php else: ?>
            <div class="grid grid-3">
                <?php foreach ($providers as $p): ?>
                    <a class="card stack" href="<?= e(url('providers/' . $p['slug'])) ?>" style="text-decoration:none;color:inherit">
                        <h3 style="margin:0"><?= $this->e((string) $p['business_name']) ?></h3>
                        <div>
                            <?= $p['is_verified'] ? '<span class="badge badge-verified">Verified</span> ' : '' ?>
                            <?= $p['is_founding_provider'] ? '<span class="badge badge-confirmed">Founding</span> ' : '' ?>
                            <?= !empty($p['is_unclaimed']) ? '<span class="badge badge-neutral">Unclaimed</span> ' : '' ?>
                            <span class="badge badge-neutral"><?= $this->e(ucfirst((string) $p['service_model'])) ?></span>
                        </div>
                        <?php if ($p['town_name']): ?><p class="muted" style="margin:0"><?= $this->e((string) $p['town_name']) ?></p><?php endif; ?>
                        <?php if ($p['description']): ?><p style="margin:0"><?= $this->e(mb_substr((string) $p['description'], 0, 120)) ?><?= mb_strlen((string) $p['description']) > 120 ? '…' : '' ?></p><?php endif; ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <div class="btn-row" style="margin-top:1.5rem;justify-content:center">
                    <?php if ($page > 1): ?>
                        <a class="btn btn-ghost" href="<?= e(url('providers' . $qs(['page' => $page - 1]))) ?>">&laquo; Previous</a>
                    <?php endif; ?>
                    <span class="muted" style="align-self:center">Page <?= $page ?> of <?= $pages ?></span>
                    <?php if ($page < $pages): ?>
                        <a class="btn btn-ghost" href="<?= e(url('providers' . $qs(['page' => $page + 1]))) ?>">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
