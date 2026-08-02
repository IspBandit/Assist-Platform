<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container">
        <h1>Compare RVs</h1>
        <p>Compare up to four models. Missing values stay blank — never zero. Highlight differences only when useful.</p>

        <form method="get" action="<?= e(url('compare')) ?>" class="polaris-compare-picker">
            <p class="muted">Select up to four published models:</p>
            <div class="polaris-compare-options">
                <?php foreach ($catalogue as $model): ?>
                    <label>
                        <input type="checkbox" name="id[]" value="<?= (int) $model['id'] ?>"
                            <?= in_array((int) $model['id'], $selectedIds, true) ? 'checked' : '' ?>>
                        <?= $this->e($model['manufacturer_name'] . ' ' . $model['name']) ?>
                    </label>
                <?php endforeach; ?>
            </div>
            <label class="polaris-check"><input type="checkbox" name="diff" value="1" <?= !empty($differencesOnly) ? 'checked' : '' ?>> Show differences only</label>
            <button class="btn btn-primary" type="submit">Compare</button>
        </form>

        <?php if ($comparison['models'] === []): ?>
            <p class="empty-state">Choose models above to start a comparison.</p>
        <?php else: ?>
            <?php if (!empty($shareUrl)): ?>
                <p class="notice notice-info">Shareable link: <a href="<?= e($shareUrl) ?>"><?= $this->e($shareUrl) ?></a></p>
            <?php elseif ($selectedIds !== []): ?>
                <form method="post" action="<?= e(url('compare/share')) ?>" class="inline-form" style="margin:1rem 0">
                    <?= csrf_field() ?>
                    <?php foreach ($selectedIds as $id): ?>
                        <input type="hidden" name="ids[]" value="<?= (int) $id ?>">
                    <?php endforeach; ?>
                    <button class="btn btn-secondary" type="submit">Create shareable comparison link</button>
                </form>
            <?php endif; ?>
            <?php if (array_filter($comparison['winners']) !== []): ?>
                <ul class="polaris-trust-list" aria-label="Category winners where data exists">
                    <?php foreach ($comparison['winners'] as $key => $winner): ?>
                        <?php if ($winner === null) { continue; } ?>
                        <li><?= $this->e(ucwords(str_replace('_', ' ', $key))) ?>: <?= $this->e($winner) ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <div class="polaris-compare-table-wrap" role="region" aria-label="Comparison table" tabindex="0">
                <table class="polaris-compare-table">
                    <thead>
                        <tr>
                            <th scope="col">Specification</th>
                            <?php foreach ($comparison['models'] as $model): ?>
                                <th scope="col"><a href="<?= e($model['url']) ?>"><?= $this->e($model['name']) ?></a></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($comparison['rows'] as $row): ?>
                            <?php if (!empty($differencesOnly) && !$row['differs']) { continue; } ?>
                            <tr class="<?= $row['differs'] ? 'is-diff' : '' ?>">
                                <th scope="row"><?= $this->e($row['label']) ?></th>
                                <?php foreach ($row['values'] as $value): ?>
                                    <td><?= $value === null ? '—' : $this->e($value) ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $this->endSection(); ?>
