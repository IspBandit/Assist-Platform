<?php
/** @var array<int,array<string,mixed>> $assets */
/** @var array<string,string> $types */
/** @var array<string,string> $jurisdictions */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="garage-hero">
    <picture class="journey-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" type="image/avif" srcset="<?= e(asset('img/garage-hero-mobile.avif')) ?>">
        <source media="(max-width: 719px)" type="image/webp" srcset="<?= e(asset('img/garage-hero-mobile.webp')) ?>">
        <source type="image/avif" srcset="<?= e(asset('img/garage-hero-desktop.avif')) ?>">
        <img src="<?= e(asset('img/garage-hero-desktop.webp')) ?>" width="1824" height="864" alt="" fetchpriority="high">
    </picture>
    <div class="journey-hero-shade" aria-hidden="true"></div>
    <div class="container garage-hero-inner">
        <div>
            <span class="product-kicker">Shared across Assist Platform</span>
            <h1>My Garage</h1>
            <p>Keep each vehicle, trailer, caravan or motorhome in one private profile, then use it across <?= $this->e(current_brand()->name()) ?> and the other Assist services.</p>
        </div>
        <div class="garage-trust-card">
            <strong>One profile. Four specialist platforms.</strong>
            <span>Your Garage belongs to your account, not to one brand.</span>
        </div>
    </div>
</section>

<section class="section garage-section">
    <div class="container">
        <div class="garage-section-heading">
            <div><span class="eyebrow">Your vehicles and towables</span><h2>Ready when you need them</h2></div>
            <a class="btn btn-secondary" href="#add-to-garage">Add to Garage</a>
        </div>

        <?php if ($assets === []): ?>
            <div class="garage-empty">
                <span class="garage-empty-mark" aria-hidden="true">+</span>
                <h3>Your Garage is ready</h3>
                <p>Add your first vehicle, trailer, caravan or motorhome. You can attach private compliance documents and open rules already filtered for it.</p>
                <a class="btn btn-primary" href="#add-to-garage">Add your first asset</a>
            </div>
        <?php else: ?>
            <div class="garage-grid">
                <?php foreach ($assets as $asset): ?>
                    <?php
                    $makeModel = trim((string) ($asset['make'] ?? '') . ' ' . (string) ($asset['model'] ?? ''));
                    $nextExpiry = (string) ($asset['next_expiry'] ?? '');
                    ?>
                    <a class="garage-card" href="<?= e(url('account/garage/' . (int) $asset['id'])) ?>">
                        <div class="garage-card-top">
                            <span class="garage-type-mark" aria-hidden="true"><?= $this->e(strtoupper(substr((string) $asset['asset_type'], 0, 2))) ?></span>
                            <span class="badge badge-neutral"><?= $this->e($types[(string) $asset['asset_type']] ?? 'Vehicle') ?></span>
                        </div>
                        <h3><?= $this->e((string) $asset['nickname']) ?></h3>
                        <p><?= $this->e($makeModel !== '' ? trim((string) ($asset['model_year'] ?? '') . ' ' . $makeModel) : 'Details ready to complete') ?></p>
                        <dl class="garage-card-meta">
                            <div><dt>Registered</dt><dd><?= $this->e((string) ($asset['registration_jurisdiction'] ?: 'Not set')) ?></dd></div>
                            <div><dt>Documents</dt><dd><?= (int) $asset['document_count'] ?></dd></div>
                        </dl>
                        <?php if ($nextExpiry !== ''): ?><span class="garage-expiry">Next expiry <?= $this->e(date('j M Y', strtotime($nextExpiry))) ?></span><?php endif; ?>
                        <span class="garage-card-link">Open asset <span aria-hidden="true">→</span></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section section-soft" id="add-to-garage">
    <div class="container garage-form-shell">
        <div class="garage-form-intro">
            <span class="eyebrow">Add once, use everywhere</span>
            <h2>Add to My Garage</h2>
            <p>Start with the basics. Ratings are optional and should be copied from the exact compliance plate or manufacturer information.</p>
            <ul class="garage-feature-list">
                <li>Private to your signed-in account</li>
                <li>Available on every Assist brand</li>
                <li>No registration number or VIN required</li>
            </ul>
        </div>
        <form class="card garage-form" method="post" action="<?= e(url('account/garage')) ?>">
            <?= csrf_field() ?>
            <div class="form-grid form-grid-2">
                <div class="form-group"><label for="asset_type">Type</label><select id="asset_type" name="asset_type" required><option value="">Choose a type</option><?php foreach ($types as $value => $label): ?><option value="<?= $this->e($value) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></div>
                <div class="form-group"><label for="nickname">Name in your Garage</label><input id="nickname" name="nickname" maxlength="100" required placeholder="For example: Touring caravan"></div>
                <div class="form-group"><label for="make">Make <span>optional</span></label><input id="make" name="make" maxlength="100"></div>
                <div class="form-group"><label for="model">Model <span>optional</span></label><input id="model" name="model" maxlength="100"></div>
                <div class="form-group"><label for="model_year">Year <span>optional</span></label><input id="model_year" name="model_year" type="number" min="1900" max="<?= (int) date('Y') + 2 ?>" inputmode="numeric"></div>
                <div class="form-group"><label for="registration_jurisdiction">Registration state <span>optional</span></label><select id="registration_jurisdiction" name="registration_jurisdiction"><option value="">Not set</option><?php foreach ($jurisdictions as $value => $label): ?><option value="<?= $this->e($value) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></div>
            </div>
            <details class="garage-technical-fields">
                <summary>Add plate ratings <span>optional</span></summary>
                <div class="form-grid form-grid-3">
                    <?php foreach (['tare_kg' => 'Tare', 'gvm_kg' => 'GVM', 'gcm_kg' => 'GCM', 'atm_kg' => 'ATM', 'max_braked_towing_kg' => 'Max braked towing', 'max_towball_kg' => 'Max towball'] as $field => $label): ?>
                        <div class="form-group"><label for="<?= $field ?>"><?= $label ?> (kg)</label><input id="<?= $field ?>" name="<?= $field ?>" type="number" min="0" max="9999999" step="0.1" inputmode="decimal"></div>
                    <?php endforeach; ?>
                </div>
            </details>
            <div class="form-group"><label for="notes">Private notes <span>optional</span></label><textarea id="notes" name="notes" maxlength="2000" rows="3" placeholder="Accessories, setup notes or details you want to remember"></textarea></div>
            <button class="btn btn-primary btn-lg" type="submit">Add to My Garage</button>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
