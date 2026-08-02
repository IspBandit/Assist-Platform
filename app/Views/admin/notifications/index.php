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
$nextAction = static function (array $campaign): string {
    $status = (string) ($campaign['status'] ?? 'draft');
    $stage = (string) ($campaign['delivery_stage'] ?? 'draft');
    if ($status === 'sent') { return 'View delivery results'; }
    if ($status === 'cancelled') { return 'View cancelled campaign'; }
    return match ($stage) {
        'draft' => 'Send preview to yourself',
        'test' => 'Start sending (max 25)',
        'pilot' => 'Review pilot and start 50/day',
        'daily_50' => 'Continue or approve 100/day',
        'daily_100' => 'Continue approved batches',
        default => 'Open campaign',
    };
};
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <div><p class="eyebrow">Growth</p><h1 style="margin:0">Email campaigns</h1></div>
        <a class="btn btn-primary" href="<?= e(url('admin/notifications/compose')) ?>">Create email campaign</a>
    </div>
    <div class="grid grid-3" style="margin-top:1rem">
        <div class="card" style="margin:0;text-align:center"><div class="muted">Email queue pending</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['pending'] ?? 0) ?></div></div>
        <div class="card" style="margin:0;text-align:center"><div class="muted">Sent</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['sent'] ?? 0) ?></div></div>
        <div class="card" style="margin:0;text-align:center"><div class="muted">Failed</div><div style="font-size:1.6rem;font-weight:700"><?= (int) ($queue['failed'] ?? 0) ?></div></div>
    </div>
    <p class="muted" style="margin-top:.5rem;font-size:.85rem">Two clearly separated campaign types are prepared for each VanAssist service category. <strong>Factual listing checks</strong> can include unclaimed providers backed by a recorded public source. <strong>Marketing campaigns</strong> remain held until documented consent exists. Both use a preview sent only to you, a 25-recipient pilot and reviewed daily caps.</p>
</div>

<section class="card" aria-labelledby="campaign-send-path">
    <p class="eyebrow">Clear sending path</p>
    <h2 id="campaign-send-path">How to send a campaign</h2>
    <div class="grid grid-4">
        <div><strong>1. Open a campaign</strong><p class="muted">Choose a prepared service campaign below, or create one.</p></div>
        <div><strong>2. Review recipients</strong><p class="muted">Check the live eligible list and remove anyone who should not receive it.</p></div>
        <div><strong>3. Email yourself a preview</strong><p class="muted">Confirm the sender name, wording, graphics and links before any provider receives it.</p></div>
        <div><strong>4. Start staged sending</strong><p class="muted">Send the 25-recipient pilot, review delivery, then continue at the approved daily cap.</p></div>
    </div>
    <p class="muted mb-0"><strong>The preview is a one-time safety check.</strong> It does not replace the real campaign and it is not sent to providers.</p>
</section>

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
    <h2 style="margin-top:0">Campaigns ready to review and send</h2>
    <div class="table-wrap">
        <p class="muted">Live audience counts are resolved from current provider data and suppression controls. They are not inserted into delivery records until an approved stage is queued.</p>
        <table class="data">
            <thead><tr><th>Title</th><th>Type / audience</th><th>Next action</th><th>Queued / sent</th><th>Live audience</th><th>When</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($notifications as $n): ?>
                <tr>
                    <td><strong><?= $this->e((string) $n['title']) ?></strong><br><span class="muted" style="font-size:.8rem">by <?= $this->e((string) ($n['author'] ?? 'system')) ?></span></td>
                    <td><?= $this->e((string)($n['campaign_type']??'')==='directory_accuracy' ? 'Factual listing check' : str_replace('_', ' ', (string) ($n['campaign_type'] ?? 'general_marketing'))) ?><br><span class="muted"><?= $this->e((string)($n['category_name']??$n['audience_type'])) ?></span></td>
                    <td><strong><?= $this->e($nextAction($n)) ?></strong><br><span class="muted" style="font-size:.78rem"><?= $this->e((string) $n['status']) ?> / <?= $this->e((string) ($n['delivery_stage'] ?? 'draft')) ?></span></td>
                    <td><strong><?= (int) $n['recipient_count'] ?></strong><br><span class="muted" style="font-size:.78rem">delivery records</span></td>
                    <td>
                        <?php $summary = $audienceSummaries[(int) $n['id']] ?? null; ?>
                        <?php if ($summary !== null): ?>
                            <strong><?= (int) $summary['eligible'] ?> eligible now</strong><br>
                            <span class="muted" style="font-size:.78rem"><?= (int) $summary['with_email'] ?> with email · <?= (int) $summary['held'] ?> held · <?= (int) $summary['suppressed'] ?> suppressed<?php if ((int) $summary['excluded'] > 0): ?> · <?= (int) $summary['excluded'] ?> removed<?php endif; ?></span>
                        <?php else: ?><span class="muted">Not a provider campaign</span><?php endif; ?>
                    </td>
                    <td><?= $this->e((string) ($n['scheduled_at'] ?? $n['sent_at'] ?? $n['created_at'] ?? '')) ?></td>
                    <td>
                        <?php $rowStatus = (string) $n['status']; $rowStage = (string) ($n['delivery_stage'] ?? 'draft'); ?>
                        <?php if (in_array($rowStatus, ['sent', 'cancelled'], true)): ?>
                            <a class="btn btn-ghost" href="<?= e(url('admin/notifications/show?id=' . (int) $n['id'])) ?>">View results</a>
                        <?php elseif ($rowStage === 'draft' && !empty(current_user()['email'])): ?>
                            <form method="post" action="<?= e(url('admin/notifications/test')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="test_email" value="<?= e_attr((string) current_user()['email']) ?>"><button class="btn btn-primary" type="submit">Send preview to me</button></form>
                        <?php elseif ($rowStage === 'test'): ?>
                            <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="stage" value="pilot"><button class="btn btn-primary" type="submit">Start sending (max 25)</button></form>
                        <?php elseif ($rowStage === 'pilot'): ?>
                            <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="stage" value="daily_50"><button class="btn btn-primary" type="submit">Send next batch (max 50/day)</button></form>
                        <?php elseif ($rowStage === 'daily_50'): ?>
                            <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="stage" value="daily_50"><button class="btn btn-primary" type="submit">Send next batch (50/day)</button></form>
                        <?php elseif ($rowStage === 'daily_100'): ?>
                            <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $n['id'] ?>"><input type="hidden" name="stage" value="daily_100"><button class="btn btn-primary" type="submit">Send next batch (100/day)</button></form>
                        <?php else: ?>
                            <a class="btn btn-primary" href="<?= e(url('admin/notifications/show?id=' . (int) $n['id'])) ?>">Open campaign</a>
                        <?php endif; ?>
                        <?php if (!in_array($rowStatus, ['sent', 'cancelled'], true)): ?><a class="btn btn-ghost" href="<?= e(url('admin/notifications/show?id=' . (int) $n['id'])) ?>">Review details</a><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if ($notifications === []): ?><tr><td colspan="7" class="muted">No broadcasts yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
