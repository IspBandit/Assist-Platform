<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $regions */
/** @var array $errors */
$this->extend('layouts.public');
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
$logo = $park['logo_path'] ? url('uploads-public/park-logos/' . $park['logo_path']) : null;
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:820px">
        <h1>Park profile</h1>
        <?php $this->include('partials.park-nav', ['active' => 'profile']); ?>

        <form method="post" action="<?= e(url('park/profile')) ?>" enctype="multipart/form-data" class="stack">
            <?= csrf_field() ?>

            <div class="card stack">
                <h2 style="margin-top:0">Park details</h2>
                <div class="form-group">
                    <label for="name">Park name <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required value="<?= e_attr((string) $park['name']) ?>">
                    <?= $err('name') ?>
                </div>
                <div class="form-group">
                    <label for="address">Address</label>
                    <input type="text" id="address" name="address" value="<?= e_attr((string) ($park['address'] ?? '')) ?>">
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="town_id">Town</label>
                        <select id="town_id" name="town_id">
                            <option value="">Select…</option>
                            <?php foreach ($towns as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (int) $park['town_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?> / <?= $this->e((string) $t['state_abbr']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="number_of_sites">Number of sites</label>
                        <input type="number" min="0" id="number_of_sites" name="number_of_sites" value="<?= e_attr((string) ($park['number_of_sites'] ?? '')) ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="4"><?= e((string) ($park['description'] ?? '')) ?></textarea>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label for="stay_type">Stay type</label><select id="stay_type" name="stay_type"><?php foreach (['caravan_park'=>'Caravan park','campground'=>'Campground','free_camp'=>'Free camp','showground'=>'Showground','rest_area'=>'Rest area','farm_stay'=>'Farm stay','other'=>'Other'] as $v=>$l): ?><option value="<?= e_attr($v) ?>" <?= ($park['stay_type'] ?? 'caravan_park') === $v ? 'selected' : '' ?>><?= $this->e($l) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="price_type">Cost type</label><select id="price_type" name="price_type"><?php foreach (['free'=>'Free','donation'=>'Donation','low_cost'=>'Low cost','paid'=>'Paid','unknown'=>'Check with venue'] as $v=>$l): ?><option value="<?= e_attr($v) ?>" <?= ($park['price_type'] ?? 'unknown') === $v ? 'selected' : '' ?>><?= $this->e($l) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="form-group"><label for="max_stay">Maximum stay or restrictions</label><input id="max_stay" name="max_stay" maxlength="80" value="<?= e_attr((string) ($park['max_stay'] ?? '')) ?>" placeholder="For example: 48 hours"></div>
                <fieldset><legend>Facilities</legend><div class="grid grid-3"><?php foreach (['powered_sites'=>'Powered sites','unpowered_sites'=>'Unpowered sites','toilets'=>'Toilets','showers'=>'Showers','potable_water'=>'Drinking water','dump_point'=>'Dump point','pets_allowed'=>'Pets considered'] as $field=>$label): ?><label class="checkbox"><input type="checkbox" name="<?= e_attr($field) ?>" value="1" <?= !empty($park[$field]) ? 'checked' : '' ?>> <?= $this->e($label) ?></label><?php endforeach; ?></div></fieldset>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Contact</h2>
                <div class="grid grid-2">
                    <div class="form-group"><label for="phone">Phone</label><input type="text" id="phone" name="phone" value="<?= e_attr((string) ($park['phone'] ?? '')) ?>"></div>
                    <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e_attr((string) ($park['email'] ?? '')) ?>"></div>
                </div>
                <div class="grid grid-2">
                    <div class="form-group"><label for="website">Website</label><input type="text" id="website" name="website" value="<?= e_attr((string) ($park['website'] ?? '')) ?>"></div>
                    <div class="form-group"><label for="facebook_url">Facebook</label><input type="text" id="facebook_url" name="facebook_url" value="<?= e_attr((string) ($park['facebook_url'] ?? '')) ?>"></div>
                </div>
                <div class="form-group"><label for="booking_url">Booking link</label><input type="url" id="booking_url" name="booking_url" value="<?= e_attr((string) ($park['booking_url'] ?? '')) ?>"></div>
                <div class="form-group">
                    <label for="guest_request_contact">Guest-request contact email</label>
                    <input type="email" id="guest_request_contact" name="guest_request_contact" value="<?= e_attr((string) ($park['guest_request_contact'] ?? '')) ?>">
                </div>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Logo</h2>
                <?php if ($logo !== null): ?><img src="<?= e($logo) ?>" alt="Current logo" style="max-height:80px;border-radius:8px"><?php endif; ?>
                <div class="form-group">
                    <label for="logo">Upload a logo (JPG, PNG or WebP)</label>
                    <input type="file" id="logo" name="logo" accept="image/jpeg,image/png,image/webp">
                </div>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">Public page &amp; SEO</h2>
                <label><input type="checkbox" name="public_page_enabled" value="1" <?= $park['public_page_enabled'] ? 'checked' : '' ?>> Show a public page for this park <span class="muted">(only visible once your park is approved)</span></label>
                <div class="form-group"><label for="seo_title">SEO title</label><input type="text" id="seo_title" name="seo_title" value="<?= e_attr((string) ($park['seo_title'] ?? '')) ?>"></div>
                <div class="form-group"><label for="seo_description">SEO description</label><textarea id="seo_description" name="seo_description" rows="2"><?= e((string) ($park['seo_description'] ?? '')) ?></textarea></div>
            </div>

            <div class="btn-row"><button type="submit" class="btn btn-primary">Save profile</button></div>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
