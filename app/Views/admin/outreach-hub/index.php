<?php
/** @var \App\Core\View $this */
$this->extend('layouts.admin');
/** @var array<string,int> $summary */
/** @var array<int,array<string,mixed>> $contacts */
/** @var array<int,array<string,mixed>> $campaigns */
/** @var array<int,array<string,mixed>> $recentEvents */
/** @var array<string,string> $types */
/** @var array<string,string> $statuses */
/** @var array<string,string> $outcomes */
/** @var array<string,string> $filters */
/** @var array{indexing:bool,facebook:bool,factual_campaigns:int,approved_social_assets:int} $freeChannelStatus */
/** @var array<int,array{key:string,title:string,url:string,copy:string}> $shareKits */
?>
<?php $this->section('content'); ?>
<header class="page-header outreach-hub-header">
    <div>
        <p class="eyebrow">Audience growth, with evidence</p>
        <h1>Free Growth Hub</h1>
        <p>Get <?= $this->e(current_brand()->name()) ?> in front of travellers and relevant organisations using no-cost channels, tracked links and the correct outreach boundary.</p>
    </div>
    <div class="btn-row"><a class="btn btn-primary" href="<?= e(url('admin/notifications/compose?campaign_type=organisation_outreach&audience_type=organisations')) ?>">Plan organisation campaign</a><a class="btn btn-secondary" href="<?= e(url('admin/notifications')) ?>">Email campaigns</a><?php if (can('prospects.manage')): ?><a class="btn btn-ghost" href="<?= e(url('admin/prospects')) ?>">Provider prospects</a><?php endif; ?><?php if (can('content.manage')): ?><a class="btn btn-ghost" href="<?= e(url('admin/social-media')) ?>">Social studio</a><?php endif; ?></div>
</header>

<section class="card" aria-labelledby="free-growth-actions-heading">
    <div class="campaign-recipient-heading">
        <div><p class="eyebrow">Start here</p><h2 id="free-growth-actions-heading">Free ways to grow the audience</h2><p class="muted">Use several small, relevant channels consistently. Website Insights records the tracked links below so you can see what actually sends visitors.</p></div>
        <?php if (can('demand.view')): ?><a class="btn btn-ghost" href="<?= e(url('admin/demand')) ?>">Measure results</a><?php endif; ?>
    </div>
    <div class="grid grid-3 free-growth-channel-grid">
        <article class="card">
            <span class="badge <?= $freeChannelStatus['factual_campaigns'] > 0 ? 'badge-confirmed' : 'badge-neutral' ?>"><?= (int) $freeChannelStatus['factual_campaigns'] ?> prepared</span>
            <h3>Provider listing checks</h3>
            <p>Send factual, non-promotional accuracy notices to source-backed unclaimed providers. This improves data and introduces the claim path without pretending a public email is general marketing consent.</p>
            <a class="btn btn-primary" href="<?= e(url('admin/notifications')) ?>">Open email campaigns</a>
        </article>
        <article class="card">
            <span class="badge <?= (int) $summary['eligible'] > 0 ? 'badge-confirmed' : 'badge-neutral' ?>"><?= (int) $summary['eligible'] ?> reviewed contacts</span>
            <h3>Clubs, media and partners</h3>
            <p>Approach relevant published roles with a specific member resource, correction request, story idea or partnership proposal. Review evidence below before sending.</p>
            <a class="btn btn-primary" href="#target-register-heading">Review organisations</a>
        </article>
        <article class="card">
            <span class="badge <?= $freeChannelStatus['facebook'] ? 'badge-confirmed' : 'badge-neutral' ?>"><?= $freeChannelStatus['facebook'] ? 'Facebook connected' : 'Manual sharing ready' ?></span>
            <h3>Social and community</h3>
            <p>Publish approved Page assets, then share a useful feedback-led post only where group rules allow it. Downloaded assets also work for Instagram and community pages.</p>
            <?php if (can('content.manage')): ?><a class="btn btn-primary" href="<?= e(url('admin/social-media')) ?>">Open Social Studio</a><?php endif; ?>
        </article>
        <article class="card">
            <span class="badge <?= $freeChannelStatus['indexing'] ? 'badge-confirmed' : 'badge-neutral' ?>"><?= $freeChannelStatus['indexing'] ? 'Indexing on' : 'Indexing needs attention' ?></span>
            <h3>Google and Bing discovery</h3>
            <p>Keep indexing on, submit <a href="<?= e(url('sitemap.xml')) ?>" target="_blank" rel="noopener">the sitemap</a> in Google Search Console and import it into Bing Webmaster Tools. This is free and compounds over time.</p>
            <div class="btn-row"><?php if (can('seo.manage')): ?><a class="btn btn-secondary" href="<?= e(url('admin/seo')) ?>">Check SEO settings</a><?php endif; ?><a class="btn btn-ghost" href="https://search.google.com/search-console" target="_blank" rel="noopener">Google Search Console ↗</a><a class="btn btn-ghost" href="https://www.bing.com/webmasters" target="_blank" rel="noopener">Bing Webmaster Tools ↗</a></div>
        </article>
        <article class="card">
            <span class="badge badge-neutral"><?= (int) $freeChannelStatus['approved_social_assets'] ?> approved assets</span>
            <h3>Provider and park referrals</h3>
            <p>Give claimed providers, caravan parks, dealers and rental businesses a tracked link for their website, counter sign, booking email or customer handover message.</p>
            <a class="btn btn-secondary" href="#free-share-kits">Copy partner message</a>
        </article>
        <article class="card">
            <span class="badge badge-neutral">No ad spend</span>
            <h3>Your everyday channels</h3>
            <p>Add a tracked link to your email signature, Facebook Page details and Messenger replies. Ask real users for feedback and useful corrections—not generic likes or spammy reposts.</p>
            <a class="btn btn-secondary" href="#free-share-kits">Open share kit</a>
        </article>
    </div>
</section>

<section class="card" id="free-share-kits" aria-labelledby="free-share-kits-heading">
    <div class="campaign-recipient-heading"><div><p class="eyebrow">Copy, share, measure</p><h2 id="free-share-kits-heading">Tracked free-share kit</h2><p class="muted">Each channel has its own link. Website Insights can distinguish community, Messenger, newsletter and partner traffic.</p></div></div>
    <div class="grid grid-2 free-share-kit-grid">
        <?php foreach ($shareKits as $kit): ?>
            <article class="card">
                <h3><?= $this->e($kit['title']) ?></h3>
                <label for="share-copy-<?= e_attr($kit['key']) ?>">Ready-to-use wording</label>
                <textarea id="share-copy-<?= e_attr($kit['key']) ?>" rows="7" readonly><?= $this->e($kit['copy']) ?></textarea>
                <label for="share-url-<?= e_attr($kit['key']) ?>">Tracked link</label>
                <input id="share-url-<?= e_attr($kit['key']) ?>" value="<?= e_attr($kit['url']) ?>" readonly>
                <div class="btn-row">
                    <button class="btn btn-primary" type="button" data-copy-target="#share-copy-<?= e_attr($kit['key']) ?>">Copy message</button>
                    <button class="btn btn-secondary" type="button" data-copy-target="#share-url-<?= e_attr($kit['key']) ?>">Copy link</button>
                    <button class="btn btn-ghost" type="button" data-native-share data-share-title="<?= e_attr(current_brand()->name()) ?>" data-share-text="<?= e_attr($kit['copy']) ?>" data-share-url="<?= e_attr($kit['url']) ?>">Share from device</button>
                </div>
                <p class="muted small" data-copy-status aria-live="polite"></p>
            </article>
        <?php endforeach; ?>
    </div>
    <div class="alert alert-warning"><strong>Email boundary:</strong> do not turn these messages into a scraped bulk list. Commercial email requires a defensible consent basis, sender identification and a working unsubscribe. Provider factual checks and reviewed organisation outreach remain separate controlled campaign types. <a href="https://www.acma.gov.au/avoid-sending-spam" target="_blank" rel="noopener">Read the current ACMA guidance ↗</a></div>
</section>

<div class="metric-grid outreach-summary" aria-label="Organisation outreach summary">
    <div class="metric-card"><span>Needs review</span><strong><?= (int) $summary['research'] ?></strong></div>
    <div class="metric-card"><span>Send-eligible</span><strong><?= (int) $summary['eligible'] ?></strong></div>
    <div class="metric-card"><span>Follow-ups due</span><strong><?= (int) $summary['follow_ups_due'] ?></strong></div>
    <div class="metric-card"><span>Sent by platform</span><strong><?= (int) $summary['sent_by_platform'] ?></strong></div>
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
                                    <label>Basis<select name="consent_basis"><option value="">Required only when eligible</option><?php foreach (['inferred_role_relevant'=>'Role-relevant published address','express_written'=>'Express written','express_phone'=>'Express phone','express_web'=>'Express web'] as $basisKey=>$basisLabel): ?><option value="<?= e_attr($basisKey) ?>" <?= (string) ($contact['consent_basis'] ?? '') === $basisKey ? 'selected' : '' ?>><?= $this->e($basisLabel) ?></option><?php endforeach; ?></select></label>
                                    <label>Evidence<textarea name="consent_evidence" rows="4" maxlength="1000" placeholder="Why this published role and this exact message are directly relevant; include the official source context"><?= e((string) ($contact['consent_evidence'] ?? '')) ?></textarea></label>
                                    <?php if (!empty($contact['reviewed_at'])): ?><small>Last reviewed <?= $this->e((string) $contact['reviewed_at']) ?> by user #<?= (int) $contact['reviewed_by'] ?>.</small><?php endif; ?>
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

<details class="card outreach-history" data-mobile-collapse>
    <summary><h2>Recent outreach history</h2><span>Queued, transport and outcome evidence</span></summary>
    <div class="table-wrap"><table class="data data--compact"><thead><tr><th>When</th><th>Organisation</th><th>Event</th><th>Campaign / note</th></tr></thead><tbody>
    <?php foreach ($recentEvents as $event): ?><tr><td><?= $this->e((string) $event['created_at']) ?></td><td><?= $this->e((string) $event['organisation_name']) ?></td><td><strong><?= $this->e(str_replace('_', ' ', (string) $event['event_type'])) ?></strong></td><td><?= $this->e((string) ($event['campaign_title'] ?: $event['notes'])) ?></td></tr><?php endforeach; ?>
    <?php if ($recentEvents === []): ?><tr><td colspan="4" class="muted">No outreach events recorded yet.</td></tr><?php endif; ?>
    </tbody></table></div>
</details>

<section class="card" aria-labelledby="outreach-campaign-heading">
    <div class="campaign-recipient-heading"><div><p class="eyebrow">Campaign pipeline</p><h2 id="outreach-campaign-heading">Organisation campaigns</h2></div><a class="btn btn-secondary" href="<?= e(url('admin/notifications')) ?>">All email campaigns</a></div>
    <?php if ($campaigns === []): ?><div class="empty-state"><h3>No organisation campaign yet</h3><p>Review targets first, then create one narrow campaign for the matching audience.</p></div>
    <?php else: ?><div class="table-wrap"><table class="data"><thead><tr><th>Campaign</th><th>Status</th><th>Stage</th><th>Recipients</th><th></th></tr></thead><tbody><?php foreach ($campaigns as $campaign): ?><tr><td><strong><?= $this->e((string) $campaign['title']) ?></strong><small><?= $this->e((string) $campaign['created_at']) ?></small></td><td><?= $this->e((string) $campaign['status']) ?></td><td><?= $this->e((string) $campaign['delivery_stage']) ?></td><td><?= (int) $campaign['recipient_count'] ?></td><td><a class="btn btn-ghost" href="<?= e(url('admin/notifications/show?id=' . (int) $campaign['id'])) ?>">Review</a></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php $this->endSection(); ?>
