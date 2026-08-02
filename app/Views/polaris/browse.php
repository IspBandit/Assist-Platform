<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <div class="section-heading">
            <span class="product-kicker dark">Browse</span>
            <h1>New RVs</h1>
            <p>Structured filters for category, sleeps, ATM, length and budget. Missing prices are never shown as zero.</p>
        </div>
        <form class="polaris-filter-bar polaris-filter-bar--rich" method="get" action="<?= e(url('rvs')) ?>">
            <label class="sr-only" for="polaris-q">Search</label>
            <input id="polaris-q" type="search" name="q" value="<?= e($filters['q'] ?? '') ?>" placeholder="Manufacturer or model">
            <label class="sr-only" for="polaris-category">Category</label>
            <select id="polaris-category" name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['category'] ?? '') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="polaris-production">Production status</label>
            <select id="polaris-production" name="production_status">
                <option value="">Any status</option>
                <?php foreach (['current' => 'Current', 'upcoming' => 'Upcoming', 'superseded' => 'Superseded'] as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['production_status'] ?? '') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <label class="sr-only" for="polaris-sleeps">Min sleeps</label>
            <input id="polaris-sleeps" type="number" name="min_sleeps" min="1" max="12" value="<?= e($filters['min_sleeps'] ?? '') ?>" placeholder="Min sleeps">
            <label class="sr-only" for="polaris-atm">Max ATM kg</label>
            <input id="polaris-atm" type="number" name="max_atm_kg" min="500" step="50" value="<?= e($filters['max_atm_kg'] ?? '') ?>" placeholder="Max ATM kg">
            <label class="sr-only" for="polaris-length">Max length m</label>
            <input id="polaris-length" type="number" name="max_length_m" min="1" step="0.1" value="<?= e($filters['max_length_m'] ?? '') ?>" placeholder="Max length m">
            <label class="sr-only" for="polaris-budget">Max budget AUD</label>
            <input id="polaris-budget" type="number" name="max_budget_aud" min="1000" step="1000" value="<?= e($filters['max_budget_aud'] ?? '') ?>" placeholder="Max budget AUD">
            <label class="sr-only" for="polaris-sort">Sort</label>
            <select id="polaris-sort" name="sort">
                <?php foreach ($sortOptions as $key => $label): ?>
                    <option value="<?= e($key) ?>" <?= ($filters['sort'] ?? 'name') === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-primary" type="submit">Apply filters</button>
        </form>
        <p class="muted"><?= count($models) ?> model<?= count($models) === 1 ? '' : 's' ?></p>
        <?php if (empty($models)): ?>
            <p class="empty-state">No models matched those filters.</p>
        <?php else: ?>
            <div class="polaris-model-grid">
                <?php foreach ($models as $model): ?>
                    <?php $this->include('polaris.partials.model-card', ['model' => $model]); ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
