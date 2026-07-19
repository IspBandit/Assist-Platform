<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<int,string> $vehicleTypes */
/** @var array<int,string> $urgencies */
/** @var int|null $prefillTownId */
/** @var string $prefillTownLabel */
/** @var int|null $prefillCategoryId */
/** @var string $prefillUrgency */
/** @var array<string,mixed>|null $park */
/** @var array $errors */
$this->extend('layouts.public');
$park = $park ?? null;
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$label = static fn (string $s): string => ucwords(str_replace('_', ' ', $s));
$maxImages = (int) config('uploads.max_request_images', 6);
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1>Request assistance</h1>
        <p class="muted">Tell us where you are, your vehicle and the problem. We'll review your request and coordinate suitable providers. There's no charge to submit a request.</p>

        <?php if ($park !== null): ?>
            <div class="card" style="border-left:4px solid var(--green,#0f6e6e)">
                <p style="margin:0">Referred by <strong><?= $this->e((string) $park['name']) ?></strong>. We'll note your stay when matching providers.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(url('request-assistance')) ?>" enctype="multipart/form-data" class="stack" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
            <?= csrf_field() ?>
            <?php if ($park !== null): ?><input type="hidden" name="park" value="<?= e_attr((string) $park['slug']) ?>"><?php endif; ?>
            <div class="honeypot" aria-hidden="true"><label>Leave this blank<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

            <div class="card">
                <h2>1. Location</h2>
                <div class="form-group">
                    <label for="town_search">Nearest town or suburb <span class="required">*</span></label>
                    <input type="text" id="town_search" value="<?= e_attr((string) old('town_label', $prefillTownLabel ?? '')) ?>" placeholder="Start typing town, suburb or postcode…" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="town-suggest">
                    <input type="hidden" id="town_id" name="town_id" value="<?= e_attr((string) old('town_id', (string) ($prefillTownId ?? ''))) ?>" required>
                    <div id="town-suggest" class="town-suggest" role="listbox" hidden></div>
                    <?= $err('town_id') ?>
                    <div class="btn-row" style="margin-top:.6rem">
                        <?php $this->include('partials.use-location-btn', [
                            'autoSubmit' => 'false',
                            'selectTarget' => '#town_id',
                            'postcodeTarget' => '#postcode',
                            'class' => 'use-location-mobile btn btn-secondary',
                        ]); ?>
                    </div>
                    <p class="location-status muted" role="status" aria-live="polite" hidden></p>
                </div>
                <div class="form-group">
                    <label for="location_label">Where are you staying? (park, free-camp, address area)</label>
                    <input type="text" id="location_label" name="location_label" value="<?= e_attr((string) old('location_label')) ?>">
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="postcode">Postcode</label>
                        <input type="text" id="postcode" name="postcode" value="<?= e_attr((string) old('postcode')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="max_distance_km">Willing to travel (km)</label>
                        <input type="number" id="max_distance_km" name="max_distance_km" min="0" value="<?= e_attr((string) old('max_distance_km')) ?>">
                    </div>
                </div>
                <label><input type="checkbox" name="mobile_preferred" value="1" checked> I'd prefer a mobile provider come to me</label><br>
                <label><input type="checkbox" name="workshop_acceptable" value="1" checked> I can take my van to a workshop if needed</label>
            </div>

            <div class="card">
                <h2>2. Vehicle</h2>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="vehicle_type">Vehicle type</label>
                        <select id="vehicle_type" name="vehicle_type">
                            <option value="">Select…</option>
                            <?php foreach ($vehicleTypes as $vt): ?>
                                <option value="<?= e($vt) ?>" <?= old('vehicle_type', '') === $vt ? 'selected' : '' ?>><?= $this->e($label($vt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="vehicle_year">Year</label>
                        <input type="number" id="vehicle_year" name="vehicle_year" min="1950" max="2100" value="<?= e_attr((string) old('vehicle_year')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="vehicle_make">Make</label>
                        <input type="text" id="vehicle_make" name="vehicle_make" value="<?= e_attr((string) old('vehicle_make')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="vehicle_model">Model</label>
                        <input type="text" id="vehicle_model" name="vehicle_model" value="<?= e_attr((string) old('vehicle_model')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="vehicle_length_m">Length (m)</label>
                        <input type="number" step="0.1" id="vehicle_length_m" name="vehicle_length_m" value="<?= e_attr((string) old('vehicle_length_m')) ?>">
                    </div>
                    <div class="form-group" style="align-self:flex-end">
                        <label><input type="checkbox" name="is_usable" value="1"> The van is still usable / liveable</label>
                    </div>
                </div>
            </div>

            <div class="card">
                <h2>3. Service needed</h2>
                <div class="form-group">
                    <label for="primary_category_id">Main service type <span class="required">*</span></label>
                    <select id="primary_category_id" name="primary_category_id" required>
                        <option value="">Select a service…</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) old('primary_category_id', $prefillCategoryId ?? 0) === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?= $err('primary_category_id') ?>
                </div>
                <fieldset class="form-group">
                    <legend>Other services that may apply</legend>
                    <div class="grid grid-3">
                        <?php foreach ($categories as $c): ?>
                            <label style="font-weight:400"><input type="checkbox" name="categories[]" value="<?= (int) $c['id'] ?>"> <?= $this->e((string) $c['name']) ?></label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
            </div>

            <div class="card">
                <h2>4. The problem</h2>
                <div class="form-group">
                    <label for="title">Short summary <span class="required">*</span></label>
                    <input type="text" id="title" name="title" maxlength="190" value="<?= e_attr((string) old('title')) ?>" placeholder="e.g. Fridge not cooling on 12V" required>
                    <?= $err('title') ?>
                </div>
                <div class="form-group">
                    <label for="description">Describe the issue</label>
                    <textarea id="description" name="description" rows="5"><?= e((string) old('description')) ?></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="appliance_brand">Appliance brand</label>
                        <input type="text" id="appliance_brand" name="appliance_brand" value="<?= e_attr((string) old('appliance_brand')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="appliance_model">Appliance model</label>
                        <input type="text" id="appliance_model" name="appliance_model" value="<?= e_attr((string) old('appliance_model')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="error_code">Error code (if any)</label>
                        <input type="text" id="error_code" name="error_code" value="<?= e_attr((string) old('error_code')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="urgency">Urgency</label>
                        <select id="urgency" name="urgency">
                            <?php foreach ($urgencies as $u): ?>
                                <option value="<?= e($u) ?>" <?= (old('urgency') ?: ($prefillUrgency ?? 'medium')) === $u ? 'selected' : '' ?>><?= $this->e($label($u)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="travel_deadline">I'm leaving the area on</label>
                        <input type="date" id="travel_deadline" name="travel_deadline" value="<?= e_attr((string) old('travel_deadline')) ?>">
                    </div>
                </div>
                <label><input type="checkbox" name="safety_concern" value="1"> This may be a safety concern (gas, electrical, brakes)</label><br>
                <label><input type="checkbox" name="flexible_dates" value="1" checked> My dates are flexible</label><br>
                <label><input type="checkbox" name="willing_group_day" value="1"> I'm happy to be part of a group "service run" day to reduce travel costs</label>
            </div>

            <div class="card">
                <h2>5. Photos <span class="muted" style="font-size:1rem">(optional, up to <?= $maxImages ?>)</span></h2>
                <p class="muted">Photos of the fault, data plate or set-up help providers assess the job. JPG, PNG or WEBP.</p>
                <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
            </div>

            <div class="card">
                <h2>6. Your contact details</h2>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="contact_name">Name <span class="required">*</span></label>
                        <input type="text" id="contact_name" name="contact_name" value="<?= e_attr((string) old('contact_name')) ?>" required>
                        <?= $err('contact_name') ?>
                    </div>
                    <div class="form-group">
                        <label for="contact_email">Email <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" value="<?= e_attr((string) old('contact_email')) ?>" required>
                        <?= $err('contact_email') ?>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Phone</label>
                        <input type="tel" id="contact_phone" name="contact_phone" value="<?= e_attr((string) old('contact_phone')) ?>">
                    </div>
                    <div class="form-group">
                        <label for="preferred_contact">Preferred contact</label>
                        <select id="preferred_contact" name="preferred_contact">
                            <option value="either">Either</option>
                            <option value="email">Email</option>
                            <option value="phone">Phone</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="consent_terms" value="1"> I agree to the <a href="<?= e(url('terms-of-use')) ?>" target="_blank">terms of use</a> <span class="required">*</span></label>
                    <?= $err('consent_terms') ?>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="consent_privacy" value="1"> I agree to the <a href="<?= e(url('privacy-policy')) ?>" target="_blank">privacy policy</a> <span class="required">*</span></label>
                    <?= $err('consent_privacy') ?>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="consent_share" value="1"> I'm happy for relevant details to be shared with matched providers</label>
                </div>
                <div class="form-group">
                    <label><input type="checkbox" name="marketing_opt_in" value="1"> Send me occasional VanAssist updates</label>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-lg">Submit request</button>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
