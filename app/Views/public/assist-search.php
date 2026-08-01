<?php
/** @var \App\Core\View $this */
/** @var string $query */
/** @var float|null $lat */
/** @var float|null $lng */
/** @var \App\Platform\AiSearch\Dto\SearchResponse|null $result */
/** @var string $structuredFindUrl */
/** @var string $staysUrl */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <span class="directory-eyebrow">Ask VanAssist</span>
        <h1>What do you need help finding?</h1>
        <p class="lead" style="max-width:40rem">Describe what you need in plain language. Category and town search remain available if you prefer them.</p>

        <form class="search-card" method="get" action="<?= e(url('ask')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>" style="margin:1.25rem 0 1.5rem">
            <div class="form-group mb-0 location-field">
                <label for="ask-q">Your request</label>
                <input type="text" id="ask-q" name="q" value="<?= e_attr($query) ?>" maxlength="240" placeholder="e.g. Dump point near Batehaven" autocomplete="off" required>
                <input type="hidden" name="lat" value="<?= $lat !== null ? e_attr((string) $lat) : '' ?>">
                <input type="hidden" name="lng" value="<?= $lng !== null ? e_attr((string) $lng) : '' ?>">
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline', 'autoSubmit' => 'false']); ?>
                <p class="location-status muted" role="status" aria-live="polite" hidden></p>
            </div>
            <div class="search-submit-row" style="margin-top:1rem">
                <?php $this->include('partials.use-location-btn', ['class' => 'use-location-mobile btn btn-secondary']); ?>
                <button type="submit" class="btn btn-primary btn-lg">Search</button>
                <a class="btn btn-secondary btn-lg" href="<?= e($structuredFindUrl) ?>">Use category search</a>
            </div>
        </form>

        <p class="muted" style="margin:0 0 1.5rem">Examples: public toilets near me · LPG refill near Batemans Bay · mobile caravan repairer near Emerald · caravan park nearby · auto electrician within 50 km</p>

        <?php if ($result !== null): ?>
            <?php if ($result->messages !== []): ?>
                <div class="card" style="border-left:4px solid #c9a227;margin-bottom:1rem">
                    <?php foreach ($result->messages as $message): ?>
                        <p style="margin:0.35rem 0"><?= $this->e($message) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($result->town !== null): ?>
                <p class="muted">Interpreted near <strong><?= $this->e((string) $result->town['name']) ?><?= !empty($result->town['state_abbr']) ? ', ' . $this->e((string) $result->town['state_abbr']) : '' ?></strong>
                    <?php if ($result->intent->radiusKm !== null): ?> · within <?= (int) $result->intent->radiusKm ?> km<?php endif; ?>
                    · confidence <?= number_format($result->intent->confidence * 100, 0) ?>%</p>
            <?php endif; ?>

            <?php if ($result->providers !== []): ?>
                <h2 class="h3" style="margin-top:1.5rem">Providers</h2>
                <div class="provider-results">
                    <?php foreach ($result->providers as $p): ?>
                        <?php
                        $isPossible = (int) ($p['is_inferred'] ?? 0) === 1;
                        $searchId = $result->assistSearchId;
                        $this->include('partials.provider-result-card', compact('p', 'isPossible', 'searchId'));
                        ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($result->stays !== []): ?>
                <h2 class="h3" style="margin-top:1.5rem">Places to stay</h2>
                <div class="stay-results">
                    <?php foreach ($result->stays as $stay): ?>
                        <article class="card" style="margin-bottom:0.75rem">
                            <h3 class="h4" style="margin:0 0 0.35rem">
                                <a href="<?= e(url('caravan-parks/' . ($stay['slug'] ?? ''))) ?>"><?= $this->e((string) ($stay['name'] ?? 'Stay')) ?></a>
                            </h3>
                            <p class="muted" style="margin:0">
                                <?= $this->e((string) ($stay['stay_type'] ?? '')) ?>
                                <?php if (!empty($stay['town_name'])): ?> · <?= $this->e((string) $stay['town_name']) ?><?php endif; ?>
                                <?php if (isset($stay['distance_km']) && $stay['distance_km'] !== null): ?> · <?= max(1, (int) $stay['distance_km']) ?> km straight-line<?php endif; ?>
                            </p>
                        </article>
                    <?php endforeach; ?>
                </div>
                <p style="margin-top:0.75rem"><a href="<?= e($staysUrl) ?>">Open full stays search</a></p>
            <?php endif; ?>

            <?php if ($result->searched && $result->providers === [] && $result->stays === []): ?>
                <div class="card" style="margin-top:1rem">
                    <p style="margin:0">No listings matched this Ask VanAssist search yet. <a href="<?= e($structuredFindUrl) ?>">Try category search</a> or <a href="<?= e(url('request-assistance')) ?>">request assistance</a>.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
