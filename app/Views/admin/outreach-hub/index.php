<?php
/** @var \App\Core\View $this */
$this->extend('layouts.admin');
/** @var array<string,int> $summary */
/** @var array<int,array<string,mixed>> $contacts */
/** @var array<int,array<string,mixed>> $campaigns */
/** @var array<string,string> $types */
/** @var array<string,string> $statuses */
/** @var array<string,string> $outcomes */
/** @var array<string,string> $filters */
?>
<?php $this->section('content'); ?>
<header class="page-header outreach-hub-header">
    <div>
        <p class="eyebrow">Audience growth, with evidence</p>
        <h1>PR &amp; Outreach Hub</h1>
        <p>Manage clubs, peak bodies, manufacturers, dealer and rental networks, park groups, publications and tourism partners without mixing them into provider or customer mailing lists.</p>
    </div>
    <a class="btn btn-primary" href="<?= e(url('admin/notifications/compose?campaign_type=organisation_outreach&audience_type=organisations')) ?>">Plan organisation campaign</a>
</header>

<div class="metric-grid outreach-summary" aria-label="Organisation outreach summary">
    <div class="metric-card"><span>Targets researched</span><strong><?= (int) $summary['total'] ?></strong></div>
    <div class="metric-card"><span>Needs review</span><strong><?= (int) $summary['research'] ?></strong></div>
    <div class="metric-card"><span>Send-eligible</span><strong><?= (int) $summary['eligible'] ?></strong></div>
    <div class="metric-card"><span>Held / do not contact</span><strong><?= (int) $summary['held'] + (int) $summary['do_not_contact'] ?></strong></div>
    <div class="metric-card"><span>Contacted</span><strong><?= (int) $summary['contacted'] ?></strong></div>
    <div class="metric-card"><span>Interested / shared</span><strong><?= (int) $summary['positive'] ?></strong></div>
</div>

<section class="card outreach-import" aria-labelledby="outreach-import-heading">
    <div>
        <h2 id="outreach-import-heading">Import researched organisations</h2>
        <p class="muted">An import never makes a recipient eligible. Every address starts in research or held status until its official source, published role and direct relevance are reviewed.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/outreach-hub/template')) ?>">Download CSV template</a>
        <form method="post" action="<?= e(url('admin/outreach-hub/import')) ?>" enctype="multipart/form-data" class="outreach-import-form">
            <?= csrf_field() ?>
            <label for="organisation_csv" class="sr-only">Organisation outreach CSV</label>
            <input id="organisation_csv" type="file" name="csv" accept=".csv,text/csv" required>
            <button class="btn btn-secondary" type="submit">Import for review</button>
        </form>
    </div>
</section>

<section class="card" aria-labelledby="target-register-heading">
    <div class="campaign-recipient-heading">
        <div><p class="eyebrow">Target register</p><h2 id="target-register-heading">Who we may approach</h2></div>
        <form method="get" action="<?= e(url('admin/outreach-hub')) ?>" class="outreach-filters">
            <label>Search<input type="search" name="q" value="<?= e_attr($filters['q']) ?>" placeholder="Organisation, role or email"></label>
            <label>Status<select name="status"><option value="">All statuses</option><?php foreach ($statuses as $key => $label): ?><option value="<?= e_attr($key) ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
            <label>Type<select name="type"><option value="">All target types</option><?php foreach ($types as $key => $label): ?><option value="<?= e_attr($key) ?>" <?= $filters['type'] === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
            <label>State<input name="state" value="<?= e_attr($filters['state']) ?>" maxlength="3" placeholder="QLD"></label>
            <button class="btn btn-secondary" type="submit">Filter</button>
        </form>
    </div>
    <div class="alert alert-warning"><strong>Published does not mean permission.</strong> Approve only a role or general address whose official publication context makes this specific introduction directly relevant. Never upload member lists, harvested personal addresses or contacts carrying a no-unsolicited warning.</div>
    <?php if ($contacts === []): ?>
        <div class="empty-state"><h3>No organisation targets found</h3><p>Import the evidence-backed research file or change the filters.</p></div>
    <?php else: ?>
        <div class="table-wrap outreach-table-wrap">
            <table class="data outreach-target-table">
                <thead><tr><th>Organisation</th><th>Contact and evidence</th><th>Status and outcome</th><th>Review</th></tr></thead>
                <tbody>
                <?php foreach ($contacts as $contact): ?>
                    <tr>
                        <td><strong><?= $this->e((string) $contact['organisation_name']) ?></strong><small><?= $this->e($types[(string) $contact['organisation_type']] ?? (string) $contact['organisation_type']) ?> · <?= $this->e((string) ($contact['coverage'] ?: $contact['state_code'])) ?></small><a href="<?= e_attr((string) $contact['website_url']) ?>" target="_blank" rel="noopener">Official website ↗</a></td>
                        <td><strong><?= $this->e((string) $contact['contact_role']) ?></strong><small><?= $this->e((string) $contact['email']) ?></small><small><?= $this->e((string) $contact['relevance_reason']) ?></small><a href="<?= e_attr((string) $contact['source_url']) ?>" target="_blank" rel="noopener">Evidence checked <?= $this->e((string) $contact['source_checked_at']) ?> ↗</a></td>
                        <td><span class="badge status-<?= e_attr((string) $contact['review_status']) ?>"><?= $this->e($statuses[(string) $contact['review_status']] ?? (string) $contact['review_status']) ?></span><small>Outcome: <?= $this->e($outcomes[(string) $contact['outcome_status']] ?? (string) $contact['outcome_status']) ?></small><?php if (!empty($contact['next_follow_up_at'])): ?><small>Follow up: <?= $this->e((string) $contact['next_follow_up_at']) ?></small><?php endif; ?><?php if (!empty($contact['personal_or_ambiguous'])): ?><small>Personal or ambiguous address — held</small><?php endif; ?><?php if (!empty($contact['no_unsolicited_warning'])): ?><small>No-unsolicited warning recorded</small><?php endif; ?></td>
                        <td>
                            <details>
                                <summary>Review target</summary>
                                <form method="post" action="<?= e(url('admin/outreach-hub/review')) ?>" class="campaign-recipient-action">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                    <label>Decision<select name="review_status" required><?php foreach ($statuses as $key => $label): ?><option value="<?= e_attr($key) ?>" <?= (string) $contact['review_status'] === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
                                    <label>Basis<select name="consent_basis"><option value="">Required only when eligible</option><option value="inferred_role_relevant">Role-relevant published address</option><option value="express_written">Express written</option><option value="express_phone">Express phone</option><option value="express_web">Express web</option></select></label>
                                    <label>Evidence<textarea name="consent_evidence" rows="4" maxlength="1000" placeholder="Why this published role and this exact message are directly relevant; include the official source context"></textarea></label>
                                    <button class="btn btn-primary" type="submit">Save review</button>
                                </form>
                            </details>
                            <details>
                                <summary>Record outcome</summary>
                                <form method="post" action="<?= e(url('admin/outreach-hub/outcome')) ?>" class="campaign-recipient-action">
                                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int) $contact['id'] ?>">
                                    <label>Outcome<select name="outcome_status" required><?php foreach ($outcomes as $key => $label): ?><option value="<?= e_attr($key) ?>" <?= (string) $contact['outcome_status'] === $key ? 'selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
                                    <label>Next follow-up<input type="date" name="next_follow_up" value="<?= e_attr(substr((string) ($contact['next_follow_up_at'] ?? ''), 0, 10)) ?>"></label>
                                    <label>Notes<textarea name="outcome_notes" rows="3" maxlength="1000"><?= e((string) ($contact['outcome_notes'] ?? '')) ?></textarea></label>
                                    <button class="btn btn-secondary" type="submit">Save outcome</button>
                                </form>
                            </details>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card" aria-labelledby="outreach-campaign-heading">
    <div class="campaign-recipient-heading"><div><p class="eyebrow">Campaign pipeline</p><h2 id="outreach-campaign-heading">Organisation campaigns</h2></div><a class="btn btn-secondary" href="<?= e(url('admin/notifications')) ?>">All email campaigns</a></div>
    <?php if ($campaigns === []): ?><div class="empty-state"><h3>No organisation campaign yet</h3><p>Review targets first, then create one narrow campaign for the matching audience.</p></div>
    <?php else: ?><div class="table-wrap"><table class="data"><thead><tr><th>Campaign</th><th>Status</th><th>Stage</th><th>Recipients</th><th></th></tr></thead><tbody><?php foreach ($campaigns as $campaign): ?><tr><td><strong><?= $this->e((string) $campaign['title']) ?></strong><small><?= $this->e((string) $campaign['created_at']) ?></small></td><td><?= $this->e((string) $campaign['status']) ?></td><td><?= $this->e((string) $campaign['delivery_stage']) ?></td><td><?= (int) $campaign['recipient_count'] ?></td><td><a class="btn btn-ghost" href="<?= e(url('admin/notifications/show?id=' . (int) $campaign['id'])) ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php $this->endSection(); ?>
