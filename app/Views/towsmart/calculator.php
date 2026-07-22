<?php
$this->extend('layouts.public');
$errors = $errors ?? [];
$counts = $catalogueCounts ?? ['vehicles' => 157, 'trailers' => 3769];
$field = static fn(string $key, string $default = ''): string => (string) ($values[$key] ?? old($key, $default));
$number = static function (string $name, string $label, string $help, string $value = '') use ($field): string {
    return '<div class="form-group"><label for="' . e_attr($name) . '">' . e($label) . '</label><input id="' . e_attr($name) . '" name="' . e_attr($name) . '" type="number" min="0" step="0.1" inputmode="decimal" value="' . e_attr($field($name, $value)) . '"><p class="help">' . e($help) . '</p></div>';
};
?>
<?php $this->section('head'); ?><script src="<?= e(asset('js/towsmart-calculator.js')) ?>" defer></script><?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<section class="section towsmart-workbench"><div class="container calculator-shell">
    <header class="calculator-intro">
        <span class="product-kicker dark">TowSmart combination builder</span>
        <h1>Build your real towing combination.</h1>
        <p class="lead">Choose from <?= number_format((int) $counts['vehicles']) ?> Australian tow vehicles and <?= number_format((int) $counts['trailers']) ?> caravans, campers, hybrids and trailers—or enter your own.</p>
        <div class="alert alert-info">Catalogue figures are advertised reference specifications recovered from TowWise. Confirm every rating against the exact vehicle, trailer, compliance plate and current manufacturer information.</div>
    </header>
    <?php if (!empty($errors['calculator'])): ?><div class="alert alert-error" role="alert"><?= $this->e((string) $errors['calculator']) ?></div><?php endif; ?>

    <form class="calculator-form" method="post" action="<?= e(url('calculator')) ?>" data-towsmart-calculator data-catalogue-base="<?= e_attr(url('calculator/catalogue')) ?>">
        <?= csrf_field() ?>
        <nav class="calculator-progress" aria-label="Calculator sections"><a href="#tow-vehicle">1 Vehicle</a><a href="#tow-trailer">2 Trailer</a><a href="#tow-loading">3 Loading</a><a href="#tow-review">4 Check</a></nav>

        <section class="card calculator-step" id="tow-vehicle">
            <div class="step-heading"><span>01</span><div><h2>Select your tow vehicle</h2><p>Search the Australian catalogue or switch to custom entry.</p></div></div>
            <div class="catalogue-picker">
                <label for="vehicle_catalogue_search">Vehicle make, model or year</label>
                <input id="vehicle_catalogue_search" type="search" autocomplete="off" placeholder="Start typing, e.g. Ford Ranger 2023" data-catalogue-search="vehicles" aria-controls="vehicle_catalogue_results">
                <input type="hidden" id="vehicle_catalogue_id" name="vehicle_catalogue_id" value="<?= e_attr($field('vehicle_catalogue_id')) ?>">
                <div id="vehicle_catalogue_results" class="catalogue-results" role="listbox" hidden></div>
                <button class="btn btn-ghost" type="button" data-custom-entry="vehicle">My vehicle is not listed</button>
            </div>
            <div class="selected-spec" data-selected-summary="vehicle" hidden></div>
            <div class="spec-grid" data-spec-fields="vehicle">
                <div class="form-group span-2"><label for="vehicle_name">Vehicle description</label><input id="vehicle_name" name="vehicle_name" required value="<?= e_attr($field('vehicle_name')) ?>" placeholder="Make, model, variant and year"></div>
                <?= $number('vehicle_kerb_mass', 'Kerb mass (kg)', 'Vehicle in standard condition; confirm whether fuel is included.') ?>
                <?= $number('vehicle_gvm', 'GVM (kg)', 'Maximum permitted loaded vehicle mass.') ?>
                <?= $number('vehicle_gcm', 'GCM (kg)', 'Maximum permitted combined mass.') ?>
                <?= $number('vehicle_max_braked_towing', 'Braked towing capacity (kg)', 'Maximum manufacturer-rated braked trailer mass.') ?>
                <?= $number('vehicle_max_towball', 'Towball download limit (kg)', 'Maximum permitted towball load.') ?>
                <?= $number('vehicle_front_axle_limit', 'Front axle limit (kg)', 'Optional; use the vehicle plate or handbook.') ?>
                <?= $number('vehicle_rear_axle_limit', 'Rear axle limit (kg)', 'Optional; use the vehicle plate or handbook.') ?>
            </div>
        </section>

        <section class="card calculator-step" id="tow-trailer">
            <div class="step-heading"><span>02</span><div><h2>Select your caravan or trailer</h2><p>The catalogue includes caravans, camper trailers, hybrids and other towables.</p></div></div>
            <div class="catalogue-picker">
                <label for="trailer_catalogue_search">Brand, model or trailer type</label>
                <input id="trailer_catalogue_search" type="search" autocomplete="off" placeholder="Start typing a brand or model" data-catalogue-search="trailers" aria-controls="trailer_catalogue_results">
                <input type="hidden" id="trailer_catalogue_id" name="trailer_catalogue_id" value="<?= e_attr($field('trailer_catalogue_id')) ?>">
                <div id="trailer_catalogue_results" class="catalogue-results" role="listbox" hidden></div>
                <button class="btn btn-ghost" type="button" data-custom-entry="trailer">My caravan or trailer is not listed</button>
            </div>
            <div class="selected-spec" data-selected-summary="trailer" hidden></div>
            <div class="spec-grid" data-spec-fields="trailer">
                <div class="form-group span-2"><label for="trailer_name">Trailer description</label><input id="trailer_name" name="trailer_name" required value="<?= e_attr($field('trailer_name')) ?>" placeholder="Brand, model, type and year"></div>
                <div class="form-group"><label for="trailer_type">Type</label><select id="trailer_type" name="trailer_type"><option>Caravan</option><option>Camper</option><option>Hybrid</option><option>Boat trailer</option><option>Horse float</option><option>Utility trailer</option><option>Other</option></select></div>
                <div class="form-group"><label for="trailer_axle_config">Axles</label><select id="trailer_axle_config" name="trailer_axle_config"><option>Single</option><option>Dual</option><option>Tri-axle</option></select></div>
                <?= $number('trailer_tare_mass', 'Tare mass (kg)', 'Unladen trailer mass as specified.') ?>
                <?= $number('trailer_atm', 'ATM (kg)', 'Maximum aggregate trailer mass when uncoupled.') ?>
                <?= $number('trailer_gtm', 'GTM limit (kg)', 'Maximum mass carried by the trailer wheels when coupled.') ?>
                <?= $number('trailer_tare_ball_mass', 'Advertised tare ball mass (kg)', 'Starting ball mass before your load changes.') ?>
            </div>
        </section>

        <section class="card calculator-step" id="tow-loading">
            <div class="step-heading"><span>03</span><div><h2>Add people, cargo, water and accessories</h2><p>These additions are why brochure tare figures rarely match travelling weights.</p></div></div>
            <div class="load-groups">
                <fieldset><legend>Vehicle loading</legend><div class="spec-grid">
                    <?= $number('passengers_mass', 'Passengers (kg)', 'Combined driver and passenger mass.', '0') ?>
                    <?= $number('vehicle_cargo_mass', 'Vehicle cargo (kg)', 'Luggage, tools and items carried in the vehicle.', '0') ?>
                    <?= $number('vehicle_accessories_mass', 'Vehicle accessories (kg)', 'Bullbar, winch, canopy, drawers, batteries, racks and fitted equipment.', '0') ?>
                    <?= $number('fuel_mass', 'Fuel allowance (kg)', 'Additional fuel mass not already included in the kerb figure.', '0') ?>
                </div></fieldset>
                <fieldset><legend>Trailer loading</legend><div class="spec-grid">
                    <?= $number('trailer_cargo_mass', 'Trailer cargo (kg)', 'Food, clothing, tools and loose equipment.', '0') ?>
                    <?= $number('trailer_accessories_mass', 'Accessories over axle (kg)', 'Accessories whose load is centred around the axle group.', '0') ?>
                    <?= $number('trailer_front_accessories_mass', 'Accessories forward of axle (kg)', 'Toolboxes, gas bottles, batteries or other forward-mounted additions.', '0') ?>
                    <?= $number('trailer_rear_accessories_mass', 'Accessories behind axle (kg)', 'Bike racks, spare wheels and rear-mounted additions.', '0') ?>
                </div></fieldset>
                <?php foreach ([1, 2] as $tank): ?><fieldset><legend>Water tank <?= $tank ?></legend><div class="spec-grid">
                    <?= $number('tank_' . $tank . '_litres', 'Water carried (litres)', 'One litre of water is approximately one kilogram.', '0') ?>
                    <div class="form-group"><label for="tank_<?= $tank ?>_position">Position</label><select id="tank_<?= $tank ?>_position" name="tank_<?= $tank ?>_position"><option value="front">Forward of axle</option><option value="middle" selected>Near/over axle</option><option value="behind">Behind axle</option></select><p class="help">Position affects the estimated towball load.</p></div>
                </div></fieldset><?php endforeach; ?>
                <fieldset class="accessory-builder"><legend>Detailed accessories</legend><p class="muted">Add individual fitted items as in the TowWise app. Their totals feed the calculation fields above.</p>
                    <div class="grid grid-2"><div><h3>Vehicle accessories</h3><div data-accessory-list="vehicle"></div><button class="btn btn-ghost" type="button" data-add-accessory="vehicle">+ Add vehicle accessory</button></div><div><h3>Trailer accessories</h3><div data-accessory-list="trailer"></div><button class="btn btn-ghost" type="button" data-add-accessory="trailer">+ Add trailer accessory</button></div></div>
                    <datalist id="vehicle_accessories"><option>Alloy bullbar</option><option>Steel bullbar</option><option>Winch</option><option>Roof rack</option><option>Drawer system</option><option>Second battery</option><option>Ute canopy</option><option>Long-range fuel tank</option><option>Spare wheel</option><option>Awning</option><option>Bash plates</option><option>Side steps</option><option>Fridge slide</option><option>Driving lights</option><option>UHF radio</option></datalist>
                    <datalist id="trailer_accessories"><option>Air conditioner</option><option>Annex or awning</option><option>Battery bank</option><option>Bike rack</option><option>Fridge</option><option>Gas bottles</option><option>Generator</option><option>Fuel jerry cans</option><option>Water jerry cans</option><option>Microwave</option><option>Motor mover</option><option>Satellite dish</option><option>Solar panels</option><option>Spare wheel</option><option>Toolbox</option><option>TV bracket</option><option>Water heater</option></datalist>
                </fieldset>
            </div>
        </section>

        <section class="card calculator-step calculator-submit" id="tow-review">
            <div><span class="product-kicker dark">Ready to check</span><h2>Review the margins, not just one number.</h2><p>TowSmart will compare the loaded combination against GVM, GCM, towing, ATM and towball limits.</p></div>
            <button class="btn btn-primary btn-lg" type="submit">Calculate my combination</button>
        </section>
    </form>

    <?php if (!empty($result)): ?>
    <section class="card towing-result" aria-labelledby="result-title">
        <span class="result-status result-status--<?= e_attr((string) $result['status']) ?>"><?= $this->e(str_replace('_', ' ', (string) $result['status'])) ?></span>
        <h2 id="result-title">Your combination result</h2>
        <div class="result-summary"><div><strong><?= $this->e((string) $result['calculated']['vehicle_loaded_mass']) ?> kg</strong><span>Loaded vehicle</span></div><div><strong><?= $this->e((string) $result['calculated']['trailer_gtm']) ?> kg</strong><span>Estimated trailer GTM</span></div><div><strong><?= $this->e((string) $result['calculated']['combination_mass']) ?> kg</strong><span>Combined mass · towball <?= $this->e((string) $result['calculated']['towball_percentage']) ?>%</span></div></div>
        <div class="table-wrap"><table class="data"><thead><tr><th>Check</th><th>Actual</th><th>Limit</th><th>Margin</th><th>Status</th></tr></thead><tbody><?php foreach ($result['checks'] as $check): ?><tr><td><?= $this->e($check['label']) ?></td><td><?= $this->e((string) $check['actual']) ?> kg</td><td><?= $this->e((string) $check['limit']) ?> kg</td><td><?= $this->e((string) $check['remaining']) ?> kg</td><td><?= $this->e(str_replace('_', ' ', $check['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
        <div class="alert alert-info"><?= $this->e($result['disclaimer']) ?></div>
        <?php if (auth()->check()): ?><form method="post" action="<?= e(url('account/towing-combinations')) ?>"><?= csrf_field() ?><?php foreach ($values as $key => $value): ?><input type="hidden" name="<?= e_attr($key) ?>" value="<?= e_attr((string) $value) ?>"><?php endforeach; ?><div class="form-group"><label for="label">Combination name</label><input id="label" name="label" maxlength="150" value="<?= e_attr(trim($field('vehicle_name') . ' + ' . $field('trailer_name'), ' +')) ?>"></div><button class="btn btn-secondary" type="submit">Save this combination</button></form><?php else: ?><a class="btn btn-secondary" href="<?= e(url('login')) ?>">Sign in to save this combination</a><?php endif; ?>
    </section>
    <?php endif; ?>
</div></section>
<?php $this->endSection(); ?>
