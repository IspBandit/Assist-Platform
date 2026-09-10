<?php
$this->extend('layouts.public');
$selected = array_map(static fn (array $item): int => (int) $item['id'], $items);
?>
<?php $this->section('content'); ?>
<section class="section"><div class="container">
    <nav aria-label="Breadcrumb"><a href="<?= e(url('account/towing-combinations')) ?>">Saved combinations</a> <span aria-hidden="true">/</span> <span>Compare</span></nav>
    <header class="section-heading"><span class="product-kicker dark">TowSmart comparison</span><h1>Compare saved combinations.</h1><p>Choose up to three saved snapshots. Recalculate any combination after its load, equipment, specifications or applicable rules change.</p></header>
    <form class="card" method="get" action="<?= e(url('account/towing-combinations/compare')) ?>">
        <fieldset><legend>Combinations to compare</legend><div class="grid grid-3"><?php foreach ($available as $option): ?><label><input type="checkbox" name="ids[]" value="<?= (int) $option['id'] ?>" <?= in_array((int) $option['id'], $selected, true) ? 'checked' : '' ?>> <?= $this->e((string) $option['label']) ?></label><?php endforeach; ?></div></fieldset>
        <p class="help">Only the first three selected combinations are compared.</p><button class="btn btn-primary" type="submit">Compare selected</button>
    </form>
    <?php if ($items === []): ?><div class="card"><p>Select saved combinations above to compare their recorded limits and margins.</p></div><?php else: ?>
    <div class="table-wrap" role="region" aria-label="Saved towing combination comparison" tabindex="0"><table class="data"><thead><tr><th>Measure</th><?php foreach ($items as $item): ?><th><?= $this->e((string) $item['label']) ?></th><?php endforeach; ?></tr></thead><tbody>
        <tr><th>Status</th><?php foreach ($items as $item): ?><td><?= $this->e(str_replace('_', ' ', (string) $item['result_status'])) ?></td><?php endforeach; ?></tr>
        <?php foreach (['vehicle_loaded_mass' => 'Loaded vehicle', 'trailer_gtm' => 'Estimated trailer GTM', 'combination_mass' => 'Combined mass', 'towball_mass' => 'Estimated towball mass'] as $key => $label): ?><tr><th><?= e($label) ?></th><?php foreach ($items as $item): $result=json_decode((string)$item['result_snapshot'],true)?:[]; ?><td><?= $this->e((string) ($result['calculated'][$key] ?? '—')) ?> kg</td><?php endforeach; ?></tr><?php endforeach; ?>
        <?php foreach (['vehicle_gvm' => 'Vehicle GVM', 'vehicle_gcm' => 'Vehicle GCM', 'vehicle_max_braked_towing' => 'Braked towing limit', 'trailer_atm' => 'Trailer ATM'] as $key => $label): ?><tr><th><?= e($label) ?></th><?php foreach ($items as $item): $input=json_decode((string)$item['input_snapshot'],true)?:[]; ?><td><?= $this->e((string) ($input[$key] ?? '—')) ?> kg</td><?php endforeach; ?></tr><?php endforeach; ?>
    </tbody></table></div>
    <div class="alert alert-info">This comparison uses saved estimates and is not certification. Confirm every figure for the exact vehicle, trailer and current rules before towing.</div>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
