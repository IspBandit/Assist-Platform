<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $towns */
/** @var array<int,array<string,mixed>> $categories */
/** @var array $errors */
$this->extend('layouts.public');
$err = static fn (string $k) => isset($errors[$k]) ? '<p class="field-error">' . e($errors[$k]) . '</p>' : '';
?>
<?php $this->section('content'); ?>
<section class="section">
    <div class="container" style="max-width:760px">
        <h1>Register a guest request</h1>
        <?php $this->include('partials.park-nav', ['active' => 'request']); ?>

        <p class="muted">Submit a service request on behalf of a guest. We'll review it and coordinate suitable providers. Make sure you have the guest's permission to share their contact details.</p>

        <form method="post" action="<?= e(url('park/register-request')) ?>" class="stack">
            <?= csrf_field() ?>

            <div class="card stack">
                <h2 style="margin-top:0">Guest contact</h2>
                <div class="form-group">
                    <label for="contact_name">Guest name <span class="required">*</span></label>
                    <input type="text" id="contact_name" name="contact_name" required value="<?= e_attr((string) old('contact_name')) ?>">
                    <?= $err('contact_name') ?>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="contact_email">Guest email <span class="required">*</span></label>
                        <input type="email" id="contact_email" name="contact_email" required value="<?= e_attr((string) old('contact_email')) ?>">
                        <?= $err('contact_email') ?>
                    </div>
                    <div class="form-group">
                        <label for="contact_phone">Guest phone</label>
                        <input type="text" id="contact_phone" name="contact_phone" value="<?= e_attr((string) old('contact_phone')) ?>">
                    </div>
                </div>
            </div>

            <div class="card stack">
                <h2 style="margin-top:0">The problem</h2>
                <div class="form-group">
                    <label for="title">Short summary <span class="required">*</span></label>
                    <input type="text" id="title" name="title" required value="<?= e_attr((string) old('title')) ?>" placeholder="e.g. Fridge not cooling on 12V">
                    <?= $err('title') ?>
                </div>
                <div class="grid grid-2">
                    <div class="form-group">
                        <label for="town_id">Town <span class="required">*</span></label>
                        <select id="town_id" name="town_id">
                            <option value="">Select…</option>
                            <?php foreach ($towns as $t): ?>
                                <option value="<?= (int) $t['id'] ?>" <?= (int) (old('town_id') ?: $park['town_id']) === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?= $err('town_id') ?>
                    </div>
                    <div class="form-group">
                        <label for="primary_category_id">Service type</label>
                        <select id="primary_category_id" name="primary_category_id">
                            <option value="">Not sure</option>
                            <?php foreach ($categories as $c): ?>
                                <option value="<?= (int) $c['id'] ?>" <?= (int) old('primary_category_id') === (int) $c['id'] ? 'selected' : '' ?>><?= $this->e((string) $c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label for="urgency">Urgency</label>
                    <select id="urgency" name="urgency">
                        <?php foreach (['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'urgent' => 'Urgent'] as $value => $label): ?>
                            <option value="<?= e($value) ?>" <?= old('urgency') === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="description">Details</label>
                    <textarea id="description" name="description" rows="4"><?= e((string) old('description')) ?></textarea>
                </div>
            </div>

            <div class="btn-row"><button type="submit" class="btn btn-primary">Submit request</button></div>
        </form>
    </div>
</section>
<?php $this->endSection(); ?>
