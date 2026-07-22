<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>
<section class="section"><div class="container container-narrow">
    <div class="eyebrow">Listing ownership</div>
    <h1>Claim <?= $this->e((string) $park['name']) ?></h1>
    <p>Use this form if you own or manage this caravan park, campground or stay location. VanAssist checks claims before granting Park Partner access.</p>
    <form method="post" action="<?= e(url('caravan-parks/' . $park['slug'] . '/claim')) ?>" class="card stack">
        <?= csrf_field() ?>
        <div class="form-group"><label for="claimant_name">Your name</label><input id="claimant_name" name="claimant_name" value="<?= e_attr((string) old('claimant_name')) ?>" required maxlength="150"></div>
        <div class="grid grid-2">
            <div class="form-group"><label for="claimant_email">Business email</label><input id="claimant_email" type="email" name="claimant_email" value="<?= e_attr((string) old('claimant_email')) ?>" required maxlength="190"></div>
            <div class="form-group"><label for="claimant_phone">Phone</label><input id="claimant_phone" type="tel" name="claimant_phone" value="<?= e_attr((string) old('claimant_phone')) ?>" required maxlength="40"></div>
        </div>
        <div class="form-group"><label for="relationship_to_park">Your role</label><input id="relationship_to_park" name="relationship_to_park" value="<?= e_attr((string) old('relationship_to_park')) ?>" placeholder="Owner, manager or authorised representative" required maxlength="120"></div>
        <div class="form-group"><label for="evidence_notes">How can we verify the claim?</label><textarea id="evidence_notes" name="evidence_notes" rows="5" required maxlength="2000" placeholder="For example: the business website lists this email/phone, or the council has appointed you to manage the site."><?= $this->e((string) old('evidence_notes')) ?></textarea></div>
        <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
        <label class="checkbox"><input type="checkbox" name="consent_terms" value="1" required> I confirm I am authorised to manage this listing and agree to the Park Partner terms.</label>
        <div class="actions"><button class="btn btn-primary" type="submit">Submit claim for review</button><a class="btn btn-ghost" href="<?= e(url('caravan-parks/' . $park['slug'])) ?>">Cancel</a></div>
    </form>
</div></section>
<?php $this->endSection(); ?>
