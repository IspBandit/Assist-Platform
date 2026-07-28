<?php
/** @var \App\Core\View $this */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="section"><div class="container"><h1>Credentials & campaigns</h1><?php $this->include('partials.provider-nav', ['active' => 'growth']); ?>
<div class="growth-intro"><div><span class="eyebrow">Earn trust</span><h2>Prove the specialist work you are qualified to perform.</h2><p>Capability labels require evidence and human review. A verified label is not government endorsement and automatically expires when its evidence expires.</p></div><div><span class="eyebrow">Reach relevant customers</span><h2>Promote locally without buying organic ranking.</h2><p>Campaigns remain clearly labelled, appear after authority results and report paid performance separately from organic directory visibility.</p></div></div>

<div class="growth-grid">
<section class="card"><h2>Capability credentials</h2>
    <form class="stack" method="post" action="<?= e(url('provider/growth/credential')) ?>"><?= csrf_field() ?>
        <label>Capability<select name="capability_key" required><option value="">Choose capability</option><?php foreach ($capabilityOptions as $key => $label): ?><option value="<?= $this->e($key) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
        <label>Jurisdiction<select name="jurisdiction_code"><option value="">Australia-wide / not jurisdiction-specific</option><?php foreach (['ACT','NSW','NT','QLD','SA','TAS','VIC','WA'] as $code): ?><option value="<?= $code ?>"><?= $code ?></option><?php endforeach; ?></select></label>
        <label>Evidence document<select name="evidence_document_id" required><option value="">Choose private document</option><?php foreach ($documents as $document): ?><option value="<?= (int) $document['id'] ?>"><?= $this->e((string) $document['original_name']) ?> · <?= $this->e((string) $document['verification_status']) ?></option><?php endforeach; ?></select></label>
        <label>Evidence expiry <input type="date" name="valid_until"></label>
        <p class="form-help">Documents remain private. Reviewers see the evidence; customers see only an approved capability label and its jurisdiction/validity.</p><button class="btn btn-primary" type="submit">Submit for verification</button>
    </form>
    <div class="credential-list"><?php foreach ($credentials as $credential): ?><article><div><strong><?= $this->e((string) $credential['capability_label']) ?></strong><small><?= $this->e((string) ($credential['jurisdiction_code'] ?: 'Australia-wide')) ?> · <?= $this->e((string) $credential['verification_status']) ?><?= $credential['valid_until'] ? ' · until ' . $this->e((string) $credential['valid_until']) : '' ?></small></div><?php if ($credential['verification_status'] !== 'withdrawn'): ?><form method="post" action="<?= e(url('provider/growth/credential/withdraw')) ?>"><?= csrf_field() ?><input type="hidden" name="credential_id" value="<?= (int) $credential['id'] ?>"><button class="text-button" type="submit">Withdraw</button></form><?php endif; ?></article><?php endforeach; ?></div>
</section>

<section class="card"><h2>Relevant local campaign</h2>
    <form class="stack" method="post" action="<?= e(url('provider/growth/campaign')) ?>"><?= csrf_field() ?>
        <label>Internal campaign name <input type="text" name="name" maxlength="190" required></label>
        <label>Customer-facing headline <input type="text" name="headline" maxlength="120" required></label>
        <label>Short explanation <textarea name="body" maxlength="300" rows="3"></textarea></label>
        <label>Destination URL <input type="url" name="destination_url" placeholder="https://" required></label>
        <label>Relevant service context<select name="category_id"><option value="">All approved services</option><?php foreach ($categories as $category): ?><option value="<?= (int) $category['id'] ?>"><?= $this->e((string) $category['name']) ?></option><?php endforeach; ?></select></label>
        <div class="form-grid"><label>Daily budget (AUD) <input type="number" name="daily_budget" min="1" max="100000" step="0.01" required></label><label>Total budget (AUD) <input type="number" name="total_budget" min="1" max="100000" step="0.01" required></label></div>
        <div class="form-grid"><label>Start <input type="datetime-local" name="starts_at"></label><label>End <input type="datetime-local" name="ends_at"></label></div>
        <p class="form-help">Submitting does not charge or activate anything. Platform review confirms destination safety, local relevance, budget and transparent labelling first.</p><button class="btn btn-primary" type="submit">Submit campaign for review</button>
    </form>
</section></div>

<section class="section-tight"><div class="garage-section-heading"><div><span class="eyebrow">Paid performance only</span><h2>Campaign reporting</h2></div></div><div class="campaign-report-grid"><?php foreach ($campaigns as $campaign): ?><article class="card campaign-report"><div><span class="badge badge-sponsored"><?= $this->e((string) $campaign['status']) ?></span><h3><?= $this->e((string) $campaign['name']) ?></h3></div><dl><div><dt>Impressions</dt><dd><?= number_format((int) $campaign['impressions']) ?></dd></div><div><dt>Clicks</dt><dd><?= number_format((int) $campaign['clicks']) ?></dd></div><div><dt>Conversions</dt><dd><?= number_format((int) $campaign['conversions']) ?></dd></div><div><dt>Spend</dt><dd>$<?= number_format((int) $campaign['spend_cents'] / 100, 2) ?></dd></div></dl><small>Organic directory ranking is not included or influenced.</small></article><?php endforeach; ?><?php if ($campaigns === []): ?><p class="muted">No campaigns submitted yet.</p><?php endif; ?></div></section>
</div></section>
<?php $this->endSection(); ?>
