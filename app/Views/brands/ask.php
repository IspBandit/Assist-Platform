<?php
/** @var array<string,string|null>|null $result */
$this->extend('layouts.public');
$brandName = $brand->name();
$towSmart = $brand->id() === 'towsmart';
?>
<?php $this->section('content'); ?>
<section class="section"><div class="container narrow">
    <header class="section-heading"><span class="product-kicker dark">Ask <?= $this->e($brandName) ?></span><h1><?= $towSmart ? 'What do you need to check or find?' : 'What trailer help do you need?' ?></h1><p>Describe the task and optionally add a town or suburb. <?= $this->e($brandName) ?> uses a reviewed intent matrix before showing a route.</p></header>
    <form class="search-card" method="get" action="<?= e(url('ask')) ?>">
        <div class="form-group"><label for="product-ask-q">Your request</label><input id="product-ask-q" name="q" value="<?= e_attr($query) ?>" maxlength="240" required placeholder="<?= $towSmart ? 'e.g. mobile weighing near Toowoomba' : 'e.g. trailer bearings near Bendigo' ?>" aria-describedby="product-ask-help"><p class="help" id="product-ask-help">No paid AI is required for these reviewed service and guidance routes.</p></div>
        <button class="btn btn-primary btn-lg" type="submit">Find the right path</button> <a class="btn btn-secondary btn-lg" href="<?= e(url('providers')) ?>">Browse categories</a>
    </form>
    <?php if ($result !== null): ?><section class="card" aria-labelledby="product-ask-result" aria-live="polite"><span class="product-kicker dark">What <?= $this->e($brandName) ?> understood</span><h2 id="product-ask-result"><?= $this->e((string) $result['heading']) ?></h2><p><?= $this->e((string) $result['explanation']) ?></p><?php if ($result['location'] !== null): ?><p><strong>Location:</strong> <?= $this->e((string) $result['location']) ?></p><?php endif; ?><p class="muted"><strong>Routing source:</strong> <?= $this->e((string) $result['source']) ?></p><a class="btn btn-primary" href="<?= e((string) $result['url']) ?>"><?= $result['kind'] === 'calculator' ? 'Open the towing calculator' : ($result['kind'] === 'guidance' ? 'Open guidance' : 'Open provider search') ?></a></section><?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
