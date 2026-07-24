<?php
/** @var array<string,mixed> $summary */
/** @var array<int,array<string,mixed>> $opportunities */
/** @var array<int,array<string,mixed>> $tasks */
/** @var array<int,array<string,mixed>> $states */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.admin');
$points=array_values(array_filter(array_slice($opportunities,0,500),static fn($r)=>$r['latitude']!==null&&$r['longitude']!==null));
$W=720;$H=575;$project=static fn(float $lat,float $lng):array => [max(0,min($W,($lng-112)/42.5*$W)),max(0,min($H,(-9-$lat)/35.5*$H))];
?>
<?php $this->section('content'); ?>
<div class="page-header"><div><p class="eyebrow">Platform Control Centre</p><h1>Data Intelligence</h1><p class="muted">Turn coverage, demand, verification and import-quality signals into the next best data action for <?= $this->e(current_brand()->name()) ?>.</p></div><a class="btn btn-secondary" href="<?= e(url('admin/data-sources')) ?>">Manage data sources</a></div>

<form class="card form-grid" method="get" action="<?= e(url('admin/data-intelligence')) ?>">
<label>State <select name="state_id"><option value="0">All Australia</option><?php foreach($states as $s): ?><option value="<?= (int)$s['id'] ?>" <?= (int)$filters['state_id']===(int)$s['id']?'selected':'' ?>><?= $this->e($s['name']) ?></option><?php endforeach; ?></select></label>
<label>Category <select name="category_id"><option value="0">All categories</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$filters['category_id']===(int)$c['id']?'selected':'' ?>><?= $this->e($c['name']) ?></option><?php endforeach; ?></select></label>
<div style="align-self:end"><button class="btn btn-primary">Apply filters</button></div></form>

<div class="stats-grid">
<article class="stat-card"><span>Active providers</span><strong><?= number_format((int)$summary['providers']) ?></strong><small>Selected brand</small></article>
<article class="stat-card"><span>Verification coverage</span><strong><?= number_format((float)$summary['verification_rate'],1) ?>%</strong><small><?= number_format((int)$summary['verified']) ?> verified</small></article>
<article class="stat-card"><span>Critical opportunities</span><strong><?= number_format((int)$summary['critical']) ?></strong><small>Immediate supply gaps</small></article>
<article class="stat-card"><span>Population-backed rows</span><strong><?= number_format((int)$summary['population_coverage']) ?></strong><small>Only shown where sourced data exists</small></article>
</div>

<div class="grid grid-2">
<section class="card"><h2>National coverage heat map</h2><p class="muted">Larger, darker points have a higher opportunity score. This map uses town-level aggregates only.</p>
<div class="intelligence-map"><svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-label="Australian provider coverage opportunity heat map"><?php foreach($points as $p): [$x,$y]=$project((float)$p['latitude'],(float)$p['longitude']);$radius=3+((float)$p['score']/100)*9; ?><circle cx="<?= round($x,1) ?>" cy="<?= round($y,1) ?>" r="<?= round($radius,1) ?>" opacity="<?= .25+((float)$p['score']/100)*.65 ?>"><title><?= $this->e($p['town'].' — '.$p['category'].': '.$p['score']) ?></title></circle><?php endforeach; ?></svg></div></section>
<section class="card"><h2>Import quality</h2><dl class="metric-list"><div><dt>Candidates</dt><dd><?= number_format((int)($quality['total']??0)) ?></dd></div><div><dt>Awaiting review</dt><dd><?= number_format((int)($quality['pending']??0)) ?></dd></div><div><dt>Approved</dt><dd><?= number_format((int)($quality['approved']??0)) ?></dd></div><div><dt>Merged</dt><dd><?= number_format((int)($quality['merged']??0)) ?></dd></div><div><dt>Rejected</dt><dd><?= number_format((int)($quality['rejected']??0)) ?></dd></div><div><dt>Possible duplicates</dt><dd><?= number_format((int)($quality['possible_duplicates']??0)) ?></dd></div></dl><p><a href="<?= e(url('admin/data-sources/review')) ?>">Open review queue</a></p></section>
</div>

<section class="card"><div class="page-header"><div><h2>Recommended opportunities</h2><p class="muted">Score combines provider scarcity, population pressure where available, recent zero-result demand and verification coverage.</p></div></div><div class="table-wrap"><table class="data"><thead><tr><th>Priority</th><th>Location</th><th>Category</th><th>Providers</th><th>Verified</th><th>Population</th><th>Per 10k</th><th>Score</th><th>Action</th></tr></thead><tbody>
<?php foreach(array_slice($opportunities,0,100) as $row): ?><tr><td><span class="status status-<?= e_attr($row['priority']) ?>"><?= $this->e(ucfirst($row['priority'])) ?></span></td><td><?= $this->e($row['town'].', '.$row['abbreviation']) ?></td><td><?= $this->e($row['category']) ?></td><td><?= (int)$row['providers'] ?></td><td><?= $row['verification_rate']===null?'—':e((string)$row['verification_rate']).'%' ?></td><td><?= (int)$row['population']>0?number_format((int)$row['population']):'Not available' ?></td><td><?= $row['providers_per_10000']===null?'—':e((string)$row['providers_per_10000']) ?></td><td><strong><?= e((string)$row['score']) ?></strong></td><td><form method="post" action="<?= e(url('admin/data-intelligence/tasks')) ?>"><?= csrf_field() ?><input type="hidden" name="town_id" value="<?= (int)$row['town_id'] ?>"><input type="hidden" name="category_id" value="<?= (int)$row['category_id'] ?>"><input type="hidden" name="score" value="<?= e_attr((string)$row['score']) ?>"><button class="btn btn-secondary btn-sm">Send to import workflow</button></form></td></tr><?php endforeach; ?>
<?php if(!$opportunities): ?><tr><td colspan="9" class="muted">No coverage opportunities match these filters.</td></tr><?php endif; ?></tbody></table></div></section>

<section class="card"><h2>Action queue</h2><?php if(!$tasks): ?><p class="muted">No open intelligence tasks.</p><?php endif; ?><div class="grid grid-2"><?php foreach($tasks as $task): ?><article class="task-card"><div><span class="status status-<?= e_attr($task['priority']) ?>"><?= $this->e(ucfirst($task['priority'])) ?></span><h3><?= $this->e($task['title']) ?></h3><p class="muted"><?= $this->e($task['rationale']) ?></p></div><div class="btn-row"><a class="btn btn-primary btn-sm" href="<?= e(url('admin/data-sources?intelligence_task='.(int)$task['id'])) ?>">Continue import</a><form method="post" action="<?= e(url('admin/data-intelligence/tasks/status')) ?>"><?= csrf_field() ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><button class="btn btn-ghost btn-sm" name="status" value="completed">Complete</button></form></div></article><?php endforeach; ?></div></section>
<?php $this->endSection(); ?>
