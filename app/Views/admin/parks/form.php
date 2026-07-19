<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $park */
/** @var array<int,array<string,mixed>> $towns */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0">Edit <?= $this->e((string) $park['name']) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/parks/show?id=' . (int) $park['id'])) ?>">Back</a>
    </div>

    <form method="post" action="<?= e(url('admin/parks/save')) ?>" class="stack" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $park['id'] ?>">

        <div class="form-group"><label for="name">Park name</label><input type="text" id="name" name="name" value="<?= e_attr((string) $park['name']) ?>" required></div>
        <div class="form-group"><label for="address">Address</label><input type="text" id="address" name="address" value="<?= e_attr((string) ($park['address'] ?? '')) ?>"></div>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="town_id">Town</label>
                <select id="town_id" name="town_id">
                    <option value="">None</option>
                    <?php foreach ($towns as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $park['town_id'] === (int) $t['id'] ? 'selected' : '' ?>><?= $this->e((string) $t['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group"><label for="number_of_sites">Number of sites</label><input type="number" min="0" id="number_of_sites" name="number_of_sites" value="<?= e_attr((string) ($park['number_of_sites'] ?? '')) ?>"></div>
        </div>
        <div class="grid grid-2">
            <div class="form-group"><label for="phone">Phone</label><input type="text" id="phone" name="phone" value="<?= e_attr((string) ($park['phone'] ?? '')) ?>"></div>
            <div class="form-group"><label for="email">Email</label><input type="email" id="email" name="email" value="<?= e_attr((string) ($park['email'] ?? '')) ?>"></div>
        </div>
        <div class="form-group"><label for="website">Website</label><input type="text" id="website" name="website" value="<?= e_attr((string) ($park['website'] ?? '')) ?>"></div>
        <label><input type="checkbox" name="public_page_enabled" value="1" <?= $park['public_page_enabled'] ? 'checked' : '' ?>> Public page enabled</label>

        <div class="btn-row"><button type="submit" class="btn btn-primary">Save changes</button></div>
    </form>
</div>
<?php $this->endSection(); ?>
