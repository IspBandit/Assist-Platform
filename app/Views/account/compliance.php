<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $journeys */
/** @var array<int,array<string,mixed>> $subscriptions */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>
<section class="account-hero"><div class="container"><span class="eyebrow">Shared across Assist Platform</span><h1>Compliance centre</h1><p>Your saved official-source pathways, consented alerts and specialist handoffs in one place.</p></div></section>
<section class="section"><div class="container compliance-layout">
    <main>
        <div class="garage-section-heading"><div><span class="eyebrow">Saved pathways</span><h2>What you are working through</h2></div><a class="btn btn-primary" href="<?= e(url('rules/guided')) ?>">Start a guided check</a></div>
        <?php if ($journeys === []): ?><div class="garage-empty"><h3>No saved pathways yet</h3><p>Build a guide from current official sources, then save it here with its limitations and next steps.</p></div><?php endif; ?>
        <div class="compliance-journeys">
            <?php foreach ($journeys as $journey): ?>
                <article class="card compliance-journey">
                    <div><span class="badge badge-verified"><?= $this->e((string) $journey['jurisdiction_code']) ?> official scope</span><h3><?= $this->e((string) $journey['title']) ?></h3><p><?= $this->e((string) $journey['limitation_text']) ?></p><?php if (!empty($journey['asset_name'])): ?><small>Linked to <?= $this->e((string) $journey['asset_name']) ?></small><?php endif; ?></div>
                    <div class="compliance-actions">
                        <a class="btn btn-secondary" href="<?= e(url('rules/guided?' . http_build_query(['jurisdiction' => $journey['jurisdiction_code'], 'vehicle' => $journey['vehicle_class'], 'intention' => $journey['intention']]))) ?>">Review current sources</a>
                        <form method="post" action="<?= e(url('account/compliance/subscribe')) ?>"><?= csrf_field() ?><input type="hidden" name="journey_id" value="<?= (int) $journey['id'] ?>"><label class="consent-check"><input type="checkbox" name="consent" value="yes" required> Email me when an official source in this exact scope changes.</label><button class="btn btn-ghost" type="submit">Enable alerts</button></form>
                        <form method="post" action="<?= e(url('account/compliance/handoff')) ?>"><?= csrf_field() ?><input type="hidden" name="journey_id" value="<?= (int) $journey['id'] ?>"><label class="consent-check"><input type="checkbox" name="consent" value="yes" required> Carry only jurisdiction, vehicle and job context into provider search. Do not share private files or account data.</label><button class="btn btn-primary" type="submit">Find a relevant specialist</button></form>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </main>
    <aside class="card compliance-alerts"><span class="eyebrow">Your choice</span><h2>Source alerts</h2><p>Alerts are triggered by detected official-source changes and remain subject to human review. They never silently rewrite legal guidance.</p>
        <?php foreach ($subscriptions as $subscription): ?><div class="compliance-alert"><strong><?= $this->e((string) $subscription['jurisdiction_code']) ?> · <?= $this->e((string) ($subscription['document_kind'] ?: 'all relevant rules')) ?></strong><small><?= $this->e((string) $subscription['brand_name']) ?> · <?= $this->e((string) $subscription['status']) ?></small><form method="post" action="<?= e(url('account/compliance/unsubscribe')) ?>"><?= csrf_field() ?><input type="hidden" name="subscription_id" value="<?= (int) $subscription['id'] ?>"><button class="text-button" type="submit">Stop alert</button></form></div><?php endforeach; ?>
        <?php if ($subscriptions === []): ?><p class="muted">No alerts enabled.</p><?php endif; ?>
    </aside>
</div></section>
<?php $this->endSection(); ?>
