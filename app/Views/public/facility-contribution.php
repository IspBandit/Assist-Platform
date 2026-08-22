<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container container-narrow">
    <nav class="muted" aria-label="Breadcrumb"><a href="<?= e(url('caravan-parks/'.$park['slug'])) ?>"><?= $this->e((string)$park['name']) ?></a> / Suggest a facility</nav>
    <div class="page-header"><div><p class="eyebrow">Help improve VanAssist</p><h1>Suggest a facility</h1><p>Tell us what is available at <?= $this->e((string)$park['name']) ?>. Nothing changes publicly until an administrator checks and approves it.</p></div></div>
    <?php if ($errors): ?><div class="alert alert-error" role="alert"><?= $this->e(implode(' ', $errors)) ?></div><?php endif; ?>
    <form class="card stack facility-suggestion-form" method="post" action="<?= e(url('caravan-parks/'.$park['slug'].'/suggest-facility')) ?>">
        <?= csrf_field() ?><input class="hp-field" type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true">
        <p class="notice"><strong>Current information:</strong> <?= $facilities === [] ? 'No structured facilities have been verified yet.' : count($facilities).' verified facility entries are shown on the listing.' ?></p>
        <div data-facility-items>
            <div class="facility-suggestion-item">
                <label>Facility<select name="facility_type[]" required><option value="">Choose a facility</option><?php foreach($types as $key=>$label): ?><option value="<?= e_attr($key) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
                <label>What should VanAssist say?<select name="suggested_status[]" required><option value="yes">Available</option><option value="no">Not available</option><option value="conditional">Available with conditions</option><option value="unknown">Unknown</option></select></label>
                <label>Detail or condition<input name="suggested_value[]" maxlength="120" placeholder="e.g. untreated, seasonal, designated fireplaces only"></label>
                <label>What did you observe?<textarea name="suggested_details[]" maxlength="1000" rows="3"></textarea></label>
            </div>
        </div>
        <button class="btn btn-secondary" type="button" data-add-facility-item>Add another facility</button>
        <label>General comment<textarea name="comment" maxlength="2000" rows="4" placeholder="Where is it located, and when did you see it?"></textarea></label>
        <label>Supporting source link (optional)<input type="url" name="evidence_url" maxlength="1000" placeholder="https://..."></label>
        <?php if (!auth()->check()): ?><div class="grid grid-2"><label>Your name (optional)<input name="submitter_name" maxlength="150"></label><label>Your email (optional, not public)<input type="email" name="submitter_email" maxlength="190"></label></div><?php endif; ?>
        <div class="actions"><button class="btn btn-primary" type="submit">Send for admin review</button><a class="btn btn-ghost" href="<?= e(url('caravan-parks/'.$park['slug'])) ?>">Cancel</a></div>
    </form>
</div></section>
<script>document.querySelector('[data-add-facility-item]')?.addEventListener('click',function(){const box=document.querySelector('[data-facility-items]');const item=box?.querySelector('.facility-suggestion-item');if(box&&item&&box.children.length<8){const copy=item.cloneNode(true);copy.querySelectorAll('input,textarea').forEach(el=>el.value='');box.appendChild(copy);}});</script>
<?php $this->endSection(); ?>
