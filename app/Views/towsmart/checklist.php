<?php $this->extend('layouts.public'); ?>
<?php $this->section('head'); ?><script src="<?= e(asset('js/towsmart-checklist.js')) ?>" defer></script><?php $this->endSection(); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container calculator-shell">
    <header class="section-heading"><span class="product-kicker dark">TowSmart pre-trip check</span><h1>Work through the setup before moving.</h1><p>Your progress is saved on this device. Add your own items for equipment unique to your combination.</p></header>
    <div class="alert alert-info">This practical checklist is general guidance. It does not replace your vehicle/trailer manuals, inspection requirements or applicable road rules.</div>
    <div data-tow-checklist>
    <?php foreach ([
        'Tow vehicle' => ['Tyres inspected and pressures set for the load','Wheel nuts checked as required by manufacturer','Mirrors fitted and adjusted for adequate rearward view','Lights, indicators and brake lights working','Load secured; doors, bonnet and tailgate latched','Vehicle ratings and axle margins reviewed'],
        'Trailer' => ['Coupling fully seated and positively locked','Safety chains correctly crossed/attached where required','Breakaway cable connected correctly','Jockey wheel raised, secured and clear','Stabilisers and steps fully retracted','Windows, hatches, awning and roof equipment secured','Tyres, wheel nuts and bearings checked','Gas bottles, water and internal cargo secured'],
        'Hitch and load' => ['Towball and coupling ratings suitable','Weight-distribution equipment set as instructed','Towball download checked for current load','Vehicle, trailer and axle limits checked','Front and rear accessory loads considered','No item can move during braking or cornering'],
        'Electrical and departure' => ['Trailer plug fully connected','Electric brake controller tested and adjusted','Reversing camera/monitor checked where fitted','Route, height, width and fuel stops reviewed','Final walk-around completed','Passengers clear and departure area safe'],
    ] as $group => $items): ?><section class="card checklist-group"><h2><?= $this->e($group) ?></h2><?php foreach ($items as $index => $item): ?><label class="checklist-item"><input type="checkbox" data-check-id="<?= e_attr(md5($group . $index)) ?>"><span><?= $this->e($item) ?></span></label><?php endforeach; ?></section><?php endforeach; ?>
        <section class="card checklist-group"><h2>My additional items</h2><div data-custom-checks></div><form data-add-check class="grid grid-2"><div class="form-group"><label for="custom_check">Add an item</label><input id="custom_check" maxlength="180" required placeholder="e.g. Bike rack straps checked"></div><button class="btn btn-secondary" type="submit">Add item</button></form></section>
        <div class="calculator-submit card"><p><strong data-check-progress>0 of 0 complete</strong><br><span class="muted">Stored only in this browser.</span></p><button class="btn btn-ghost" type="button" data-reset-checks>Reset checklist</button></div>
    </div>
</div></section>
<?php $this->endSection(); ?>
