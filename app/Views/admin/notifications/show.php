<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $notification */
/** @var array<int,array<string,mixed>> $recipients */
/** @var array<int,array<string,mixed>> $tests */
/** @var int $previewCount */
/** @var array{with_email:int,eligible:int,held:int,excluded:int,suppressed:int}|null $providerSummary */
/** @var array<int,array<string,mixed>> $providerCandidates */
/** @var array<string,string> $consentBases */
/** @var string $recipientSearch */
$this->extend('layouts.admin');
$status = (string) $notification['status'];
$stage = (string) ($notification['delivery_stage'] ?? 'draft');
$canCancel = !in_array($status, ['sent', 'cancelled'], true);
?>
<?php $this->section('content'); ?>
<div class="card">
    <div class="btn-row" style="justify-content:space-between">
        <h1 style="margin:0"><?= $this->e((string) $notification['title']) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/notifications')) ?>">Back</a>
    </div>
    <p class="muted">
        Status: <strong><?= $this->e($status) ?></strong> ·
        Stage: <strong><?= $this->e($stage) ?></strong> ·
        Audience: <strong><?= $this->e((string) $notification['audience_type']) ?></strong> ·
        <?php if ($status === 'sent'): ?>Sent to <strong><?= (int) $notification['recipient_count'] ?></strong> recipient(s)<?php else: ?>Estimated recipients: <strong><?= (int) $previewCount ?></strong><?php endif; ?>
    </p>
    <?php if ($providerSummary !== null && $status !== 'sent'): ?>
        <div class="alert alert-info"><strong><?= (int) $providerSummary['with_email'] ?></strong> active provider(s) have an email address in this audience. <strong><?= (int) $providerSummary['eligible'] ?></strong> can be included now; every other address remains visible below for review.</div>
    <?php endif; ?>

    <div style="border:1px solid #e3e0d8;border-radius:8px;padding:1rem;background:#fff;margin:1rem 0">
        <?= $notification['body'] /* trusted admin-authored HTML */ ?>
    </div>

    <?php if (!in_array($status, ['sent', 'cancelled'], true)): ?>
        <div class="card" style="margin:1rem 0">
            <h2 style="margin-top:0">Safe delivery stages</h2>
            <ol>
                <li>Send and inspect an internal test.</li>
                <li>Queue no more than 25 consent-eligible providers, then review replies, complaints, bounces and opt-outs.</li>
                <li>After review, queue no more than 50 in any rolling 24 hours.</li>
                <li>Only after another review, raise the hard cap to 100 in any rolling 24 hours.</li>
            </ol>
            <?php if (in_array($stage, ['draft', 'test'], true)): ?>
                <form method="post" action="<?= e(url('admin/notifications/test')) ?>" class="btn-row">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                    <label for="test_email" class="sr-only">Internal test email</label>
                    <input type="email" id="test_email" name="test_email" placeholder="Internal test email" required>
                    <button type="submit" class="btn btn-secondary">Queue internal test</button>
                </form>
            <?php endif; ?>
            <div class="btn-row" style="margin-top:1rem">
                <?php if ($stage === 'test'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="pilot"><button class="btn btn-primary">Test checked — queue pilot (max 25)</button></form>
                <?php elseif ($stage === 'pilot'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_50"><button class="btn btn-primary">Pilot reviewed — start 50/day</button></form>
                <?php elseif ($stage === 'daily_50'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_50"><button class="btn btn-secondary">Queue next batch (50/day cap)</button></form>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_100"><button class="btn btn-primary">50/day reviewed — raise to 100/day</button></form>
                <?php elseif ($stage === 'daily_100'): ?>
                    <form method="post" action="<?= e(url('admin/notifications/stage')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="stage" value="daily_100"><button class="btn btn-primary">Queue next batch (100/day cap)</button></form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($canCancel): ?>
        <div class="btn-row">
            <form method="post" action="<?= e(url('admin/notifications/cancel')) ?>" style="margin:0">
                <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
                <button type="submit" class="btn btn-ghost">Cancel campaign and pending email</button>
            </form>
        </div>
    <?php endif; ?>
</div>

<?php if ($providerSummary !== null && !in_array($status, ['sent', 'cancelled'], true)): ?>
<section class="card campaign-recipient-card" aria-labelledby="provider-recipient-heading">
    <div class="campaign-recipient-heading">
        <div>
            <p class="eyebrow">Campaign audience</p>
            <h2 id="provider-recipient-heading">Provider recipients</h2>
            <p class="muted">Review every matching provider with an email. A public business address is not, by itself, permission to send marketing email.</p>
        </div>
        <form method="get" action="<?= e(url('admin/notifications/show')) ?>" class="campaign-recipient-search">
            <input type="hidden" name="id" value="<?= (int) $notification['id'] ?>">
            <label for="recipient_search" class="sr-only">Search providers or email addresses</label>
            <input id="recipient_search" type="search" name="recipient_search" value="<?= e_attr($recipientSearch) ?>" placeholder="Search provider or email">
            <button class="btn btn-secondary" type="submit">Search</button>
        </form>
    </div>
    <div class="campaign-recipient-summary" aria-label="Recipient review summary">
        <span><strong><?= (int) $providerSummary['with_email'] ?></strong> with email</span>
        <span class="is-eligible"><strong><?= (int) $providerSummary['eligible'] ?></strong> eligible</span>
        <span class="is-held"><strong><?= (int) $providerSummary['held'] ?></strong> held</span>
        <span><strong><?= (int) $providerSummary['excluded'] ?></strong> removed</span>
        <span class="is-suppressed"><strong><?= (int) $providerSummary['suppressed'] ?></strong> suppressed</span>
    </div>
    <div class="alert alert-warning"><strong>Before adding anyone:</strong> record the real consent basis, date and evidence. Suppressed addresses cannot be restored here, and campaign removal takes effect before any batch is queued.</div>

    <?php if ($providerCandidates === []): ?>
        <div class="empty-state"><h3>No matching providers</h3><p>Try another search or confirm that this service category has active provider listings with valid email addresses.</p></div>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data campaign-recipient-table">
                <thead><tr><th>Provider</th><th>Review status</th><th>Consent record</th><th>Campaign action</th></tr></thead>
                <tbody>
                <?php foreach ($providerCandidates as $candidate): ?>
                    <?php $candidateStatus = (string) $candidate['status']; ?>
                    <tr>
                        <td><strong><?= $this->e((string) $candidate['business_name']) ?></strong><small><?= $this->e((string) $candidate['email']) ?></small></td>
                        <td><span class="badge campaign-recipient-status status-<?= e_attr($candidateStatus) ?>"><?= $this->e(ucfirst($candidateStatus)) ?></span><?php if ($candidateStatus === 'excluded'): ?><small><?= $this->e((string) $candidate['exclusion_reason']) ?></small><?php elseif ($candidateStatus === 'suppressed'): ?><small><?= $this->e((string) $candidate['suppression_reason']) ?></small><?php endif; ?></td>
                        <td>
                            <?php if (!empty($candidate['has_documented_consent'])): ?>
                                <strong><?= $this->e($consentBases[(string) $candidate['marketing_consent_source']] ?? (string) $candidate['marketing_consent_source']) ?></strong>
                                <small><?= $this->e(substr((string) $candidate['marketing_consented_at'], 0, 10)) ?> · <?= $this->e((string) $candidate['marketing_consent_evidence']) ?></small>
                            <?php else: ?><span class="muted">No complete evidence recorded</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($candidateStatus === 'eligible'): ?>
                                <form method="post" action="<?= e(url('admin/notifications/recipient-exclude')) ?>" class="campaign-recipient-action">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="provider_id" value="<?= (int) $candidate['provider_id'] ?>">
                                    <label>Removal note <input name="reason" maxlength="500" placeholder="Optional internal reason"></label>
                                    <button class="btn btn-ghost" type="submit">Remove from campaign</button>
                                </form>
                            <?php elseif ($candidateStatus === 'excluded' && !empty($candidate['has_documented_consent'])): ?>
                                <form method="post" action="<?= e(url('admin/notifications/recipient-restore')) ?>" class="campaign-recipient-action">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="provider_id" value="<?= (int) $candidate['provider_id'] ?>">
                                    <button class="btn btn-secondary" type="submit">Restore recipient</button>
                                </form>
                            <?php elseif ($candidateStatus !== 'suppressed'): ?>
                                <details class="campaign-recipient-consent">
                                    <summary>Record consent and add</summary>
                                    <form method="post" action="<?= e(url('admin/notifications/recipient-include')) ?>" class="campaign-recipient-action">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="provider_id" value="<?= (int) $candidate['provider_id'] ?>">
                                        <label>Consent basis<select name="consent_basis" required><option value="">Choose basis</option><?php foreach ($consentBases as $value => $label): ?><option value="<?= e_attr($value) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
                                        <label>Date obtained<input type="date" name="consented_at" max="<?= e_attr(date('Y-m-d')) ?>" value="<?= e_attr(date('Y-m-d')) ?>" required></label>
                                        <label>Evidence<textarea name="consent_evidence" rows="3" maxlength="500" minlength="10" placeholder="Who agreed, how, and where the record is kept" required></textarea></label>
                                        <button class="btn btn-primary" type="submit">Record and add</button>
                                    </form>
                                </details>
                                <?php if ($candidateStatus === 'held'): ?>
                                    <form method="post" action="<?= e(url('admin/notifications/recipient-exclude')) ?>" class="campaign-recipient-action campaign-recipient-remove-held">
                                        <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $notification['id'] ?>"><input type="hidden" name="provider_id" value="<?= (int) $candidate['provider_id'] ?>"><input type="hidden" name="reason" value="Removed from the campaign review pool by an administrator.">
                                        <button class="btn btn-ghost" type="submit">Remove candidate</button>
                                    </form>
                                <?php endif; ?>
                            <?php else: ?><span class="muted">Blocked platform-wide</span><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ((int) $providerSummary['with_email'] > count($providerCandidates) && $recipientSearch === ''): ?><p class="muted">Showing the first <?= count($providerCandidates) ?> providers. Use search to find a specific recipient.</p><?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if ($tests !== []): ?>
<div class="card"><h2 style="margin-top:0">Internal tests</h2><ul><?php foreach ($tests as $test): ?><li><?= $this->e((string) $test['recipient_email']) ?> — <?= $this->e((string) $test['created_at']) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<?php if ($recipients !== []): ?>
<div class="card">
    <h2 style="margin-top:0">Recipients (<?= count($recipients) ?> shown)</h2>
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Email</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($recipients as $r): ?>
                <tr><td><?= $this->e((string) $r['email']) ?></td><td><?= $this->e((string) $r['status']) ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
<?php $this->endSection(); ?>
