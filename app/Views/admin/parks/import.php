<?php
/** @var array<int,array<string,mixed>> $candidates */
/** @var array<int,array<string,mixed>> $jobs */
/** @var array<string,mixed>|null $selectedJob */
/** @var array<string,string> $stayTypes */
/** @var array<string,mixed> $filters */
/** @var array<string,int> $summary */
$this->extend('layouts.admin');
$pages=(int)ceil(max(1,$total)/$perPage);
$query=static function(array $extra=[])use($filters):string{
    $values=array_filter([
        'status'=>$filters['status']??'pending','stay_type'=>$filters['stay_type']??'',
        'state'=>$filters['state']??'','duplicate'=>$filters['duplicate']??'','q'=>$filters['q']??'',
    ]+$extra,static fn($value):bool=>$value!==''&&$value!==null);
    return $values===[]?'':'?'.http_build_query($values);
};
$returnTo='/admin/parks/import'.$query(['page'=>$page]);
$statuses=['pending'=>'Pending','held'=>'Held','approved'=>'Draft created','merged'=>'Merged','rejected'=>'Rejected'];
$authorityTypes=['free_camp','national_park','showground','rest_area','council_camp'];
?>
<?php $this->section('content'); ?>
<div class="page-header"><div><p class="eyebrow">VanAssist data trust</p><h1>Stay discovery review</h1><p class="muted">Turn the paid Queensland discovery pack into checked draft listings. Nothing on this page publishes automatically.</p></div><a class="btn btn-ghost" href="<?= e(url('admin/parks')) ?>">Places to stay</a></div>

<details class="card" <?= $selectedJob?'open':'' ?>>
    <summary><strong>Upload the Queensland caravan-stay discovery pack</strong></summary>
    <?php if($selectedJob): ?>
        <p><strong><?= number_format((int)$selectedJob['processed_lines']) ?></strong> rows screened · <strong><?= number_format((int)$selectedJob['candidates_new']) ?></strong> candidates added · <strong><?= number_format((int)$selectedJob['skipped_lines']) ?></strong> skipped · status <span class="badge badge-neutral"><?= $this->e((string)$selectedJob['status']) ?></span></p>
        <?php $errors=json_decode((string)($selectedJob['errors_json']??'[]'),true)?:[]; if($errors): ?><div class="alert alert-warning"><strong>Rows needing attention</strong><ul><?php foreach($errors as $error): ?><li>Line <?= (int)($error['line']??0) ?>: <?= $this->e((string)($error['error']??'Unknown error')) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if(in_array((string)$selectedJob['status'],['queued','running'],true)): ?><form method="post" action="<?= e(url('admin/parks/import/process')) ?>" data-auto-submit="1200"><?= csrf_field() ?><input type="hidden" name="job_id" value="<?= (int)$selectedJob['id'] ?>"><button class="btn btn-primary">Continue safe screening</button></form><?php else: ?><p class="notice notice-success">Screening finished. Review candidates below.</p><?php endif; ?>
    <?php else: ?>
        <p>Upload <code>stay-candidates-2026-07-29.jsonl</code>. The file is staged privately, expires if abandoned and creates review records only.</p>
        <form method="post" action="<?= e(url('admin/parks/import/upload')) ?>" enctype="multipart/form-data" class="form-stack"><?= csrf_field() ?><label>Discovery JSONL<input type="file" name="discovery_file" accept=".jsonl,application/x-ndjson" required></label><button class="btn btn-primary">Stage for review</button></form>
    <?php endif; ?>
    <?php if($jobs): ?><p class="muted">Recent jobs: <?php foreach($jobs as $job): ?><a href="<?= e(url('admin/parks/import?job='.(int)$job['id'])) ?>">#<?= (int)$job['id'] ?> <?= $this->e((string)$job['status']) ?></a>&nbsp; <?php endforeach; ?></p><?php endif; ?>
</details>

<section class="card">
    <div class="btn-row"><?php foreach($statuses as $key=>$label): ?><a class="btn <?= ($filters['status']??'pending')===$key?'btn-primary':'btn-ghost' ?>" href="<?= e(url('admin/parks/import'.$query(['status'=>$key,'page'=>1]))) ?>"><?= $this->e($label) ?> (<?= number_format((int)($summary[$key]??0)) ?>)</a><?php endforeach; ?></div>
    <form method="get" action="<?= e(url('admin/parks/import')) ?>" class="grid grid-3">
        <label>Search<input type="search" name="q" value="<?= e_attr((string)($filters['q']??'')) ?>" placeholder="Name, address or phone"></label>
        <label>Stay type<select name="stay_type"><option value="">All types</option><?php foreach($stayTypes as $key=>$label): ?><option value="<?= e_attr($key) ?>" <?= ($filters['stay_type']??'')===$key?'selected':'' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></label>
        <label>Possible duplicate<select name="duplicate"><option value="">All</option><option value="yes" <?= ($filters['duplicate']??'')==='yes'?'selected':'' ?>>Possible duplicates</option><option value="no" <?= ($filters['duplicate']??'')==='no'?'selected':'' ?>>No duplicate match</option></select></label>
        <input type="hidden" name="state" value="QLD"><input type="hidden" name="status" value="<?= e_attr((string)($filters['status']??'pending')) ?>">
        <div class="btn-row"><button class="btn btn-secondary">Apply filters</button><a class="btn btn-ghost" href="<?= e(url('admin/parks/import')) ?>">Reset</a></div>
    </form>
</section>

<?php if(!$candidates): ?><section class="card empty-state"><h2>No matching candidates</h2><p>Upload a discovery pack or try another review status.</p></section><?php else: ?>
<section class="card"><p><strong><?= number_format($total) ?> matching candidates</strong></p><p class="muted">Approval creates a private draft only. Open the resulting stay listing for a final accuracy check before enabling its public page.</p>
<div class="table-wrap"><table class="data review-queue-table"><thead><tr><th>Place</th><th>Type</th><th>Duplicate</th><th>Review</th></tr></thead><tbody>
<?php foreach($candidates as $candidate): $requiresAuthority=in_array((string)$candidate['stay_type'],$authorityTypes,true); ?>
<tr>
    <td data-label="Place"><strong><?= $this->e((string)$candidate['name']) ?></strong><br><span class="muted"><?= $this->e((string)($candidate['address']??'Address unavailable')) ?></span><br><?= $this->e((string)($candidate['phone']??'')) ?><?php if(!empty($candidate['website'])): ?> · <a href="<?= e_attr((string)$candidate['website']) ?>" target="_blank" rel="noopener">Discovered website</a><?php endif; ?><?php if(!empty($candidate['hold_reason'])): ?><br><span class="badge badge-neutral"><?= $this->e((string)$candidate['hold_reason']) ?></span><?php endif; ?></td>
    <td data-label="Type"><?= $this->e($stayTypes[(string)$candidate['stay_type']]??'Other') ?><br><span class="muted"><?= $this->e(ucfirst(str_replace('_',' ',(string)$candidate['price_type']))) ?></span></td>
    <td data-label="Duplicate"><?php if($candidate['duplicate_park_id']): ?><strong><?= (int)$candidate['duplicate_score'] ?>%</strong><br><?= $this->e((string)$candidate['duplicate_name']) ?> #<?= (int)$candidate['duplicate_park_id'] ?><?php else: ?>No exact contact/name match<?php endif; ?></td>
    <td data-label="Review"><details><summary>Open review</summary><form method="post" action="<?= e(url('admin/parks/import/review')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="candidate_id" value="<?= (int)$candidate['id'] ?>"><input type="hidden" name="return_to" value="<?= e_attr($returnTo) ?>">
        <label>Independent evidence URL<input type="url" name="evidence_url" value="<?= e_attr((string)($candidate['evidence_url']??'')) ?>" placeholder="<?= $requiresAuthority?'Current council or .gov.au page':'Current operator or authority page' ?>"></label>
        <?php if($requiresAuthority): ?><p class="notice notice-warning">This type can affect overnight legality. A current Australian government or council source is required.</p><?php endif; ?>
        <label>Review notes<textarea name="review_notes" rows="3" maxlength="1000" placeholder="Access, permits, restrictions, fees and caravan suitability confirmed"><?= $this->e((string)($candidate['review_notes']??'')) ?></textarea></label>
        <label><input type="checkbox" name="retention_confirmed" value="1"> I opened the independent source and confirmed the facts may be retained in a private draft.</label>
        <label>Existing stay ID for merge<input type="number" min="1" name="park_id" value="<?= (int)($candidate['duplicate_park_id']??0)?:'' ?>"></label>
        <div class="btn-row"><button class="btn btn-primary" name="decision" value="approve">Create private draft</button><button class="btn btn-secondary" name="decision" value="merge">Link existing listing</button><?php if((string)$candidate['review_status']==='held'): ?><button class="btn btn-ghost" name="decision" value="restore">Return to pending</button><?php else: ?><button class="btn btn-ghost" name="decision" value="hold">Hold</button><?php endif; ?><button class="btn btn-ghost" name="decision" value="reject">Reject</button></div>
    </form></details></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php if($pages>1): ?><div class="btn-row"><?php if($page>1): ?><a class="btn btn-ghost" href="<?= e(url('admin/parks/import'.$query(['page'=>$page-1]))) ?>">&laquo; Previous</a><?php endif; ?><span class="muted">Page <?= $page ?> of <?= $pages ?></span><?php if($page<$pages): ?><a class="btn btn-ghost" href="<?= e(url('admin/parks/import'.$query(['page'=>$page+1]))) ?>">Next &raquo;</a><?php endif; ?></div><?php endif; ?>
</section><?php endif; ?>
<?php $this->endSection(); ?>
