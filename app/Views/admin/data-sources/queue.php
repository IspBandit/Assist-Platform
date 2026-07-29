<?php
/** @var array<int,array<string,mixed>> $candidates */
/** @var array<int,array<string,mixed>> $categories */
/** @var array<string,mixed> $filters */
/** @var array<string,int> $summary */
/** @var array<string,mixed>|null $nationalImportJob */
/** @var array<string,mixed>|null $eligibleQueueRun */
$this->extend('layouts.admin');
$pages = (int)ceil(max(1, $total) / $perPage);
$filterQuery = static function (array $extra = []) use ($filters): string {
    $base = [
        'status'=>$filters['status']??'pending', 'state'=>$filters['state']??'',
        'category'=>$filters['category']??0, 'evidence'=>$filters['evidence']??'',
        'duplicate'=>$filters['duplicate']??'', 'contact'=>$filters['contact']??'',
        'route'=>$filters['route']??'', 'q'=>$filters['search']??'',
    ];
    $params = array_filter($base + $extra, static fn($value): bool => $value !== '' && $value !== 0 && $value !== null);
    return $params === [] ? '' : '?' . http_build_query($params);
};
$returnTo = '/admin/data-sources/review' . $filterQuery(['page'=>$page]);
$statuses = ['pending'=>'Pending review','held'=>'Held','approved'=>'Approved','merged'=>'Merged','rejected'=>'Rejected'];
$stateNames = ['ACT'=>'ACT','NSW'=>'New South Wales','NT'=>'Northern Territory','QLD'=>'Queensland','SA'=>'South Australia','TAS'=>'Tasmania','VIC'=>'Victoria','WA'=>'Western Australia'];
$jobScope = $nationalImportJob ? (json_decode((string)($nationalImportJob['scope_json']??'{}'), true) ?: []) : [];
?>
<?php $this->section('content'); ?>

<div class="page-header">
    <div>
        <p class="eyebrow">Data quality</p>
        <h1>Import review</h1>
        <p class="muted">Confirm services and independent evidence before anything becomes a public listing.</p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('admin/data-sources')) ?>">Data sources</a>
</div>

<?php if ($isVanAssist): ?>
<details class="card" <?= $nationalImportJob ? 'open' : '' ?>>
    <summary><strong>National caravan-route discovery file</strong></summary>
    <?php if ($nationalImportJob): ?>
        <p>
            <strong><?= number_format((int)($nationalImportJob['candidates_found']??0)) ?></strong> rows screened ·
            <strong><?= number_format((int)($nationalImportJob['candidates_new']??0)) ?></strong> candidates queued ·
            status: <span class="badge badge-neutral"><?= $this->e((string)$nationalImportJob['status']) ?></span>
        </p>
        <?php if((int)($jobScope['skipped_lines']??0)>0): ?>
            <div class="alert alert-warning"><strong><?= number_format((int)$jobScope['skipped_lines']) ?> rows need attention.</strong> They were not silently imported.<?php if(!empty($jobScope['errors'])): ?><details><summary>Show recorded errors</summary><ul><?php foreach((array)$jobScope['errors'] as $error): ?><li>Line <?= (int)($error['line']??0) ?>: <?= $this->e((string)($error['error']??'Unknown import error')) ?></li><?php endforeach; ?></ul></details><?php endif; ?></div>
        <?php endif; ?>
        <?php if (in_array((string)$nationalImportJob['status'], ['queued','running'], true)): ?>
            <p class="muted">The file is being screened in safe 500-row batches. Keep this page open until processing finishes.</p>
            <form id="national-route-process" method="post" action="<?= e(url('admin/data-sources/national-route/process')) ?>" data-auto-submit="1200">
                <?= csrf_field() ?>
                <input type="hidden" name="job_id" value="<?= (int)$nationalImportJob['id'] ?>">
                <button class="btn btn-primary" type="submit">Continue screening now</button>
            </form>
        <?php else: ?>
            <p class="notice notice-success">Screening complete. Use the filters below to review the resulting candidates.</p>
        <?php endif; ?>
    <?php else: ?>
        <p>Upload the generated <code>.jsonl.gz</code> review file. Uploading does not publish providers or grant marketing consent.</p>
        <form method="post" action="<?= e(url('admin/data-sources/national-route/upload')) ?>" enctype="multipart/form-data" class="form-stack">
            <?= csrf_field() ?>
            <label>Discovery file
                <input type="file" name="discovery_file" accept=".jsonl,.gz,application/gzip" required>
            </label>
            <p class="muted">Maximum 25 MB. The compressed file is recommended.</p>
            <button class="btn btn-primary" type="submit">Stage for safe review</button>
        </form>
    <?php endif; ?>
</details>
<?php endif; ?>

<section class="card">
    <div class="btn-row">
        <?php foreach ($statuses as $key=>$label): ?>
            <a class="btn <?= ($filters['status']??'pending')===$key?'btn-primary':'btn-ghost' ?>" href="<?= e(url('admin/data-sources/review' . $filterQuery(['status'=>$key,'page'=>1]))) ?>">
                <?= $this->e($label) ?> (<?= number_format((int)($summary[$key]??0)) ?>)
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get" action="<?= e(url('admin/data-sources/review')) ?>" class="grid grid-3">
        <label>Search
            <input type="search" name="q" value="<?= e_attr((string)($filters['search']??'')) ?>" placeholder="Business, address, phone or website">
        </label>
        <label>State
            <select name="state"><option value="">All states</option><?php foreach($stateNames as $code=>$name): ?><option value="<?= e_attr($code) ?>" <?= ($filters['state']??'')===$code?'selected':'' ?>><?= $this->e($name) ?></option><?php endforeach; ?></select>
        </label>
        <label>Route hub
            <input type="search" name="route" value="<?= e_attr((string)($filters['route']??'')) ?>" placeholder="e.g. Dubbo">
        </label>
        <label>Suggested service
            <select name="category"><option value="0">All services</option><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)($filters['category']??0)===(int)$category['id']?'selected':'' ?>><?= $this->e((string)$category['name']) ?></option><?php endforeach; ?></select>
        </label>
        <label>Evidence
            <select name="evidence"><option value="">Any evidence state</option><option value="required" <?= ($filters['evidence']??'')==='required'?'selected':'' ?>>Evidence required</option><option value="confirmed" <?= ($filters['evidence']??'')==='confirmed'?'selected':'' ?>>Evidence confirmed</option><option value="claimed" <?= ($filters['evidence']??'')==='claimed'?'selected':'' ?>>Provider claimed</option></select>
        </label>
        <label>Possible duplicate
            <select name="duplicate"><option value="">All candidates</option><option value="yes" <?= ($filters['duplicate']??'')==='yes'?'selected':'' ?>>Possible duplicates</option><option value="no" <?= ($filters['duplicate']??'')==='no'?'selected':'' ?>>No duplicate match</option></select>
        </label>
        <label>Contact availability
            <select name="contact"><option value="">Any contact detail</option><option value="both" <?= ($filters['contact']??'')==='both'?'selected':'' ?>>Phone and website</option><option value="phone" <?= ($filters['contact']??'')==='phone'?'selected':'' ?>>Has phone</option><option value="website" <?= ($filters['contact']??'')==='website'?'selected':'' ?>>Has website</option><option value="none" <?= ($filters['contact']??'')==='none'?'selected':'' ?>>No phone or website</option></select>
        </label>
        <label>Status
            <select name="status"><?php foreach($statuses as $key=>$label): ?><option value="<?= e_attr($key) ?>" <?= ($filters['status']??'pending')===$key?'selected':'' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select>
        </label>
        <div class="btn-row"><button class="btn btn-secondary" type="submit">Apply filters</button><a class="btn btn-ghost" href="<?= e(url('admin/data-sources/review')) ?>">Reset</a></div>
    </form>
</section>

<?php if (!$candidates): ?>
<section class="card empty-state"><h2>No matching candidates</h2><p>Try another status or widen the filters.</p></section>
<?php else: ?>
<section class="card">
    <?php if (!empty($eligibleQueueRun)): ?>
        <div class="alert alert-info">
            <strong>Safeguarded queue processing is continuing automatically.</strong>
            <?= number_format((int)($eligibleQueueRun['merged']??0)) ?> duplicates merged ·
            <?= number_format((int)($eligibleQueueRun['approved']??0)) ?> eligible providers published ·
            <?= number_format((int)($eligibleQueueRun['remaining']??0)) ?> eligible records remain.
        </div>
        <form method="post" action="<?= e(url('admin/data-sources/review/process-eligible')) ?>" data-auto-submit="1200">
            <?= csrf_field() ?><input type="hidden" name="run_token" value="<?= e_attr((string)$eligibleQueueRun['token']) ?>">
            <button class="btn btn-primary" type="submit">Continue safeguarded processing now</button>
        </form>
    <?php endif; ?>
    <div class="btn-row">
        <strong><?= number_format($total) ?> matching candidates</strong>
        <?php if ($isVanAssist && ($filters['status']??'pending') === 'pending' && empty($eligibleQueueRun)): ?>
        <form method="post" action="<?= e(url('admin/data-sources/review/process-eligible')) ?>">
            <?= csrf_field() ?><input type="hidden" name="confirmed" value="1">
            <?php foreach (['state','category','evidence','duplicate','contact','route'] as $filterKey): ?>
                <input type="hidden" name="<?= e_attr($filterKey) ?>" value="<?= e_attr((string)($filters[$filterKey]??'')) ?>">
            <?php endforeach; ?>
            <input type="hidden" name="q" value="<?= e_attr((string)($filters['search']??'')) ?>">
            <button class="btn btn-primary" type="submit">Process every eligible filtered record</button>
        </form>
        <?php endif; ?>
        <form method="post" action="<?= e(url('admin/data-sources/review/resolve-exact')) ?>" onsubmit="return confirm('Automatically link strong duplicate candidates to existing unclaimed providers? No provider details will be overwritten.');">
            <?= csrf_field() ?><button class="btn btn-primary" type="submit">Auto-resolve 70%+ duplicates</button>
        </form>
        <form id="bulk-review-form" method="post" action="<?= e(url('admin/data-sources/review/bulk')) ?>" class="btn-row">
            <?= csrf_field() ?><input type="hidden" name="return_to" value="<?= e_attr($returnTo) ?>">
            <select name="bulk_decision" required><option value="">Selected records…</option><option value="approve_eligible">Approve eligible new listings</option><option value="merge_exact_duplicates">Merge 70%+ duplicates</option><option value="hold">Place on hold</option><option value="reject">Reject</option><option value="restore">Return to pending</option></select>
            <label class="review-bulk-confirm"><input type="checkbox" name="bulk_confirmed" value="1"> Confirm controlled bulk action</label>
            <button class="btn btn-secondary" type="submit">Apply to selected</button>
        </form>
    </div>
    <p class="muted"><strong>One-click eligible processing:</strong> runs in bounded, resumable batches; merges safe 70%+ unclaimed duplicates first, then publishes only nonduplicates whose service, independent evidence and retention rights are already confirmed. Ineligible records stay in review with reason counts.</p>
    <p class="muted"><strong>Safety rules:</strong> approval only accepts independently confirmed records with a mapped service and no duplicate. Duplicate merge requires an unclaimed target, at least 70% confidence, a strong business-name match and an exact phone or website match. Claimed provider details are never changed.</p>
    <div class="table-wrap">
        <table class="data review-queue-table">
            <thead><tr><th><span class="sr-only">Select</span></th><th>Business</th><th>Route</th><th>Suggested service</th><th>Evidence</th><th>Duplicate</th><th>Review</th></tr></thead>
            <tbody>
            <?php foreach($candidates as $candidate):
                $raw = json_decode((string)($candidate['raw_json']??'{}'), true) ?: [];
                $queries = array_map('strval', (array)($raw['discovery_queries']??[]));
                $suggestions = array_map('strval', (array)($raw['category_slugs']??[]));
            ?>
                <tr>
                    <td data-label="Select"><label class="review-select"><input form="bulk-review-form" type="checkbox" name="candidate_ids[]" value="<?= (int)$candidate['id'] ?>"><span>Select this record</span><span class="sr-only">: <?= $this->e((string)$candidate['business_name']) ?></span></label></td>
                    <td data-label="Business"><strong><?= $this->e((string)$candidate['business_name']) ?></strong><br><span class="muted"><?= $this->e((string)($candidate['formatted_address']??'Address unavailable')) ?></span><br><?= $this->e((string)($candidate['phone']??'')) ?><?php if(!empty($candidate['website'])): ?> · <a href="<?= e_attr((string)$candidate['website']) ?>" target="_blank" rel="noopener">Website</a><?php endif; ?></td>
                    <td data-label="Route"><?= $this->e((string)($candidate['route_hub']??'—')) ?><br><span class="badge badge-neutral"><?= $this->e((string)($candidate['candidate_state']??'—')) ?></span></td>
                    <td data-label="Suggested service"><?= $this->e((string)($candidate['category_name']??'Unmapped')) ?><br><span class="muted">Confidence <?= (int)$candidate['confidence'] ?>%</span></td>
                    <td data-label="Evidence"><span class="badge <?= ($candidate['evidence_status']??'required')==='confirmed'?'badge-verified':'badge-neutral' ?>"><?= $this->e(str_replace('_',' ',(string)($candidate['evidence_status']??'required'))) ?></span><?php if(!empty($candidate['hold_reason'])): ?><br><span class="muted"><?= $this->e((string)$candidate['hold_reason']) ?></span><?php endif; ?></td>
                    <td data-label="Duplicate"><?php if(!empty($candidate['duplicate_provider_id'])): ?><span class="badge badge-confirmed"><?= (int)$candidate['duplicate_score'] ?>%</span><br><?= $this->e((string)$candidate['duplicate_name']) ?> #<?= (int)$candidate['duplicate_provider_id'] ?><?php else: ?>—<?php endif; ?></td>
                    <td data-label="Review"><details><summary>Open review</summary>
                        <form method="post" action="<?= e(url('admin/data-sources/review')) ?>" class="form-stack">
                            <?= csrf_field() ?><input type="hidden" name="candidate_id" value="<?= (int)$candidate['id'] ?>"><input type="hidden" name="return_to" value="<?= e_attr($returnTo) ?>">
                            <p><strong>Why it appeared</strong><br><span class="muted"><?= $this->e(implode(' · ', $queries) ?: 'Discovery query unavailable') ?></span></p>
                            <p><strong>Query-based suggestions</strong><br><span class="muted"><?= $this->e(implode(', ', $suggestions) ?: 'None') ?></span></p>
                            <label>Confirmed service category<select name="category_id" required><?php foreach($categories as $category): ?><option value="<?= (int)$category['id'] ?>" <?= (int)$candidate['category_id']===(int)$category['id']?'selected':'' ?>><?= $this->e((string)$category['name']) ?></option><?php endforeach; ?></select></label>
                            <label>Independent evidence URL<input type="url" name="evidence_url" value="<?= e_attr((string)($candidate['evidence_url']??$candidate['website']??'')) ?>" placeholder="Business website or authoritative register"></label>
                            <label>Review notes<textarea name="review_notes" rows="3" placeholder="What on the source confirms this service?"><?= $this->e((string)($candidate['review_notes']??'')) ?></textarea></label>
                            <label><input type="checkbox" name="retention_confirmed" value="1"> I opened the independent source and confirmed the business and selected service may be retained and published.</label>
                            <label>Merge target provider ID<input type="number" min="1" name="provider_id" value="<?= (int)($candidate['duplicate_provider_id']??0) ?: '' ?>"></label>
                            <div class="btn-row">
                                <button class="btn btn-secondary" name="decision" value="confirm">Confirm evidence for bulk approval</button>
                                <button class="btn btn-primary" name="decision" value="approve">Approve new listing</button>
                                <button class="btn btn-secondary" name="decision" value="merge">Merge</button>
                                <?php if(($candidate['review_status']??'pending')==='held'): ?><button class="btn btn-ghost" name="decision" value="restore">Return to pending</button><?php else: ?><button class="btn btn-ghost" name="decision" value="hold">Hold</button><?php endif; ?>
                                <button class="btn btn-ghost" name="decision" value="reject">Reject</button>
                            </div>
                        </form>
                    </details></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if($pages>1): ?><div class="btn-row"><?php if($page>1): ?><a class="btn btn-ghost" href="<?= e(url('admin/data-sources/review'.$filterQuery(['page'=>$page-1]))) ?>">&laquo; Previous</a><?php endif; ?><span class="muted">Page <?= $page ?> of <?= $pages ?></span><?php if($page<$pages): ?><a class="btn btn-ghost" href="<?= e(url('admin/data-sources/review'.$filterQuery(['page'=>$page+1]))) ?>">Next &raquo;</a><?php endif; ?></div><?php endif; ?>
</section>
<?php endif; ?>

<?php $this->endSection(); ?>
