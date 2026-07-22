<?php
$this->extend('layouts.public');
$errors = $errors ?? [];
$field = static fn(string $key): string => (string) ($values[$key] ?? old($key, ''));
$groups = [
    'Tow vehicle limits' => [
        'help' => 'Use the vehicle compliance plate and manufacturer information.',
        'fields' => [
            'vehicle_gvm' => ['Vehicle GVM','Maximum permitted loaded vehicle mass.'],
            'vehicle_gcm' => ['Vehicle GCM','Maximum permitted combined vehicle and trailer mass.'],
            'vehicle_max_braked_towing' => ['Maximum braked towing capacity','Manufacturer-rated maximum for a braked trailer.'],
            'vehicle_max_towball' => ['Maximum towball download','Maximum load permitted on the vehicle towball.'],
        ],
    ],
    'Your loaded combination' => [
        'help' => 'Use current scale or weighbridge figures—not brochure tare values.',
        'fields' => [
            'vehicle_mass_before_ball' => ['Loaded vehicle mass before towball','Vehicle, passengers, fuel, accessories and cargo before towball download.'],
            'trailer_atm' => ['Trailer ATM','Maximum loaded trailer mass when uncoupled.'],
            'trailer_loaded_mass' => ['Actual loaded trailer mass','Current trailer mass including cargo, water and accessories.'],
            'towball_mass' => ['Actual towball mass','Load transferred from the trailer to the tow vehicle.'],
        ],
    ],
];
?>
<?php $this->section('content'); ?>
<section class="section"><div class="container calculator-shell">
    <div class="calculator-intro"><span class="product-kicker dark">TowSmart weight check</span><h1>Towing weight calculator</h1><p class="lead">Enter all figures in kilograms. We’ll check five headline limits and show the remaining margin.</p><div class="alert alert-info">General information only. Confirm ratings, actual loaded weights and requirements that apply to your exact vehicle, trailer and jurisdiction.</div></div>
    <?php if (!empty($errors['calculator'])): ?><div class="alert alert-error" role="alert"><?= $this->e((string) $errors['calculator']) ?></div><?php endif; ?>
    <form class="card calculator-form" method="post" action="<?= e(url('calculator')) ?>">
        <?= csrf_field() ?>
        <?php foreach ($groups as $legend => $group): ?>
            <fieldset class="calculator-group"><legend><?= $this->e($legend) ?><span><?= $this->e($group['help']) ?></span></legend><div class="grid grid-2">
                <?php foreach ($group['fields'] as $key => $details): ?><div class="form-group"><label for="<?= e_attr($key) ?>"><?= $this->e($details[0]) ?> (kg)</label><input id="<?= e_attr($key) ?>" name="<?= e_attr($key) ?>" type="number" min="0" step="0.1" required inputmode="decimal" value="<?= e_attr($field($key)) ?>" aria-describedby="<?= e_attr($key) ?>-help"><p class="help" id="<?= e_attr($key) ?>-help"><?= $this->e($details[1]) ?></p></div><?php endforeach; ?>
            </div></fieldset>
        <?php endforeach; ?>
        <button class="btn btn-primary btn-lg btn-block" type="submit">Calculate my limits</button>
    </form>

    <?php if (!empty($result)): ?>
    <section class="card" aria-labelledby="result-title" style="margin-top:1.5rem">
        <span class="result-status"><?= $this->e(str_replace('_',' ',(string)$result['status'])) ?></span><h2 id="result-title" style="margin-top:.75rem">Your combination result</h2>
        <div class="result-summary"><div><strong><?= $this->e((string)$result['calculated']['vehicle_loaded_mass']) ?> kg</strong><span>Loaded vehicle</span></div><div><strong><?= $this->e((string)$result['calculated']['trailer_gtm']) ?> kg</strong><span>Calculated trailer GTM</span></div><div><strong><?= $this->e((string)$result['calculated']['combination_mass']) ?> kg</strong><span>Combined mass · towball <?= $this->e((string)$result['calculated']['towball_percentage']) ?>%</span></div></div>
        <div class="table-wrap"><table class="data"><thead><tr><th>Check</th><th>Actual</th><th>Limit</th><th>Remaining</th><th>Status</th></tr></thead><tbody><?php foreach ($result['checks'] as $check): ?><tr><td><?= $this->e($check['label']) ?></td><td><?= $this->e((string)$check['actual']) ?> kg</td><td><?= $this->e((string)$check['limit']) ?> kg</td><td><?= $this->e((string)$check['remaining']) ?> kg</td><td><?= $this->e(str_replace('_',' ',$check['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <div class="alert alert-info" style="margin-top:1rem"><?= $this->e($result['disclaimer']) ?></div>
        <?php if (auth()->check()): ?><form method="post" action="<?= e(url('account/towing-combinations')) ?>"><?= csrf_field() ?><?php foreach ($values as $key=>$value): ?><input type="hidden" name="<?= e_attr($key) ?>" value="<?= e_attr((string)$value) ?>"><?php endforeach; ?><div class="form-group"><label for="label">Combination name</label><input id="label" name="label" maxlength="150" value="My towing combination"></div><button class="btn btn-secondary" type="submit">Save this combination</button></form><?php else: ?><p><a class="btn btn-secondary" href="<?= e(url('login')) ?>">Sign in to save this result</a></p><?php endif; ?>
    </section>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
