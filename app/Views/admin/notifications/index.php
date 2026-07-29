<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $notifications */
/** @var array<int,array{with_email:int,eligible:int,held:int,excluded:int,suppressed:int}> $audienceSummaries */
/** @var array<string,int> $queue */
$this->extend('layouts.admin');
$factualCampaigns=0;$marketingCampaigns=0;$factualEligible=0;$marketingEligible=0;
foreach($notifications as $campaign){
    $type=(string)($campaign['campaign_type']??'');$summary=$audienceSummaries[(int)$campaign['id']]??null;
    if($type==='directory_accuracy'){$factualCampaigns++;$factualEligible+=(int)($summary['eligible']??0);}
    if($type==='provider_marketing'){$marketingCampaigns++;$marketingEligible+=(int)($summary['eligible']??0);}
}
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <div><p class="eyebrow">Growth</p><h1 style="margin:0">Provider email campaigns</h1></div>
        <a class="btn btn-primary" href="<?= e(url('admin/notifications/compose')) ?>">New broadcast</a>
    </div>
    <div class="grid grid-3" style="margin-top:1rem">
        <div class="card" style="margin:0;text-align:center"><div class="muted">Email queue pending</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['pending'] ?? 0) ?></div></div>
        <div class="card" style="margin:0;text-align:center"><div class="muted">Sent</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['sent'] ?? 0) ?></div></div>
        <div class="card" style="margin:0;text-align:center"><div class="muted">Failed</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['failed'] ?? 0) ?></div></div>
    </div>
    <p class="muted" style="margin-top:.5rem;font-size:.85rem">Two clearly separated campaign types are prepared for each VanAssist service category. <strong>Factual listing checks</strong> can include unclaimed providers backed by a recorded public source. <strong>Marketing campaigns</strong> remain held until documented consent exists. Both require an internal test, a 25-recipient pilot and reviewed daily caps.</p>
</div>

<div class="grid grid-2 campaign-type-overview">
    <section class="card">
        <p class="eyebrow">Can contact now after staged review</p>
        <h2>Factual listing checks</h2>
        <p><strong><?= number_format($factualEligible) ?></strong> source-backed provider email address(es) are currently eligible across <strong><?= number_format($factualCampaigns) ?></strong> prepared campaigns.</p>
        <p class="muted">Fixed non-promotional wording asks the business to confirm, correct or remove its public unclaimed record. Start with an internal test, then the 25-provider pilot.</p>
        <a class="btn btn-primary" href="#campaign-list">Review factual campaigns</a>
    </section>
    <section class="card">
        <p class="eyebrow">Consent required</p>
        <h2>Provider marketing</h2>
        <p><strong><?= number_format($marketingEligible) ?></strong> documented-consent address(es) are currently eligible across <strong><?= number_format($marketingCampaigns) ?></strong> service-specific campaigns.</p>
        <p class="muted">Addresses without recorded consent stay visible for review but cannot be bulk marketed to.</p>
        <a class="btn btn-secondary" href="#campaign-list">Review marketing campaigns</a>
    </section>
</div>

<div class="card" id="campaign-list">
    <h2 style="margin-top:0">Recent broadcasts</h2>
    <div class="table-wrap">
        <p class="muted">Live audience counts are resolved from current provider data and suppression controls. They are not inserted into delivery records until an approved stage is queued.</p>
        <table class="data">
            <thead><tr><th>Title</th><th>Type / audience</th><th>Status / stage</th><th>Queued / sent</th><th>Live audience</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($notifications as $n): ?>
                <tr>
                    <td><strong><?= $this->e((string) $n['title']) ?></strong><br><span class="muted" style="font-size:.8rem">by <?= $this->e((string) ($n['author'] ?? 'system')) ?></span></td>
                    <td><?= $this->e((string)($n['campaign_type']??'')==='directory_accuracy' ? 'Factual listing check' : str_replace('_', ' ', (string) ($n['campaign_type'] ?? 'general_marketing'))) ?><br><span class="muted"><?= $this->e((string)($n['category_name']??$n['audience_type'])) ?></span></td>
                    <td><?= $this->e((string) $n['status']) ?> / <?= $this->e((string) ($n['delivery_stage'] ?? 'draft')) ?></td>
                    <td><strong><?= (int) $n['recipient_count'] ?></strong><br><span class="muted" style="font-size:.78rem">delivery records</span></td>
                    <td>
                        <?php $summary = $audienceSummaries[(int) $n['id']] ?? null; ?>
                        <?php if ($summary !== null): ?>
                            <strong><?= (int) $summary['eligible'] ?> eligible now</strong><br>
                            <span class="muted" style="font-size:.78rem"><?= (int) $summary['with_email'] ?> with email · <?= (int) $summary['held'] ?> held · <?= (int) $summary['suppressed'] ?> suppressed<?php if ((int) $summary['excluded'] > 0): ?> · <?= (int) $summary['excluded'] ?> removed<?php endif; ?></span>
                        <?php else: ?><span class="muted">Not a provider campaign</span><?php endif; ?>
                    </td>
                    <td><?= $this->e((string) ($n['scheduled_at'] ?? $n['sent_at'] ?? $n['created_at'] ?? '')) ?></td>
                    <td><a class="btn btn-ghost" href="<?= e(url('admin/notifications/show?id=' . (int) $n['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><tr><td colspan="7" class="muted">No broadcasts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
