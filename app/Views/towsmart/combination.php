<?php
/** @var array<string,mixed> $item */
/** @var array<string,mixed> $input */
/** @var array<string,mixed> $result */
$this->extend('layouts.public');
$calculated = is_array($result['calculated'] ?? null) ? $result['calculated'] : [];
$checks = is_array($result['checks'] ?? null) ? $result['checks'] : [];
?>
<?php $this->section('content'); ?>
<section class="section"><div class="container calculator-shell">
    <nav aria-label="Breadcrumb"><a href="<?= e(url('account/towing-combinations')) ?>">Saved combinations</a> <span aria-hidden="true">/</span> <span><?= $this->e((string) $item['label']) ?></span></nav>
    <header class="section-heading">
        <span class="product-kicker dark">Saved TowSmart check</span>
        <h1><?= $this->e((string) $item['label']) ?></h1>
        <p>Saved <?= $this->e((string) $item['created_at']) ?>. This is a snapshot of the figures and guidance at that time, not current certification.</p>
    </header>
    <section class="card towing-result" aria-labelledby="saved-result-title">
        <span class="result-status result-status--<?= e_attr((string) $item['result_status']) ?>"><?= $this->e(str_replace('_', ' ', (string) $item['result_status'])) ?></span>
        <h2 id="saved-result-title">Saved result</h2>
        <div class="result-summary">
            <div><strong><?= $this->e((string) ($calculated['vehicle_loaded_mass'] ?? '—')) ?> kg</strong><span>Loaded vehicle</span></div>
            <div><strong><?= $this->e((string) ($calculated['trailer_gtm'] ?? '—')) ?> kg</strong><span>Estimated trailer GTM</span></div>
            <div><strong><?= $this->e((string) ($calculated['combination_mass'] ?? '—')) ?> kg</strong><span>Combined mass</span></div>
        </div>
        <?php if ($checks !== []): ?><div class="table-wrap"><table class="data"><thead><tr><th>Check</th><th>Actual</th><th>Limit</th><th>Margin</th><th>Status</th></tr></thead><tbody><?php foreach ($checks as $check): if (!is_array($check)) { continue; } ?><tr><td><?= $this->e((string) ($check['label'] ?? 'Check')) ?></td><td><?= $this->e((string) ($check['actual'] ?? '—')) ?> kg</td><td><?= $this->e((string) ($check['limit'] ?? '—')) ?> kg</td><td><?= $this->e((string) ($check['remaining'] ?? '—')) ?> kg</td><td><?= $this->e(str_replace('_', ' ', (string) ($check['status'] ?? 'unknown'))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        <div class="alert alert-info"><?= $this->e((string) ($result['disclaimer'] ?? 'Verify all figures against the exact vehicle, trailer, compliance plates and current manufacturer information before towing.')) ?></div>
    </section>
    <section class="card">
        <h2>Saved inputs</h2>
        <dl class="summary-list"><div><dt>Vehicle</dt><dd><?= $this->e((string) ($input['vehicle_name'] ?? 'Not recorded')) ?></dd></div><div><dt>Trailer</dt><dd><?= $this->e((string) ($input['trailer_name'] ?? 'Not recorded')) ?></dd></div><div><dt>Vehicle GVM</dt><dd><?= $this->e((string) ($input['vehicle_gvm'] ?? '—')) ?> kg</dd></div><div><dt>Trailer ATM</dt><dd><?= $this->e((string) ($input['trailer_atm'] ?? '—')) ?> kg</dd></div></dl>
        <p class="muted">Use your browser's print function if you need a paper or PDF copy. Re-run the calculator when loads, accessories, specifications or rules change.</p>
    </section>
    <div class="btn-row print-hidden"><a class="btn btn-primary" href="<?= e(url('account/towing-combinations/' . (int) $item['id'] . '/edit')) ?>">Edit and recalculate</a><button class="btn btn-secondary" type="button" onclick="window.print()">Print or save PDF</button><a class="btn btn-ghost" href="<?= e(url('account/towing-combinations/compare?ids[]=' . (int) $item['id'])) ?>">Compare</a><form method="post" action="<?= e(url('account/towing-combinations/' . (int) $item['id'] . '/remove')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Remove saved combination</button></form></div>
</div></section>
<?php $this->endSection(); ?>
