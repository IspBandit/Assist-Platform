<?php
/** @var array<string,mixed> $summary */
/** @var array<int,array<string,mixed>> $opportunities */
/** @var array<int,array<string,mixed>> $tasks */
/** @var array<int,array<string,mixed>> $states */
/** @var array<int,array<string,mixed>> $categories */
$this->extend('layouts.admin');
$points=array_values(array_filter(array_slice($opportunities,0,500),static fn($r)=>$r['latitude']!==null&&$r['longitude']!==null));
$W=720;$H=575;$project=static fn(float $lat,float $lng):array => [max(0,min($W,($lng-112)/42.5*$W)),max(0,min($H,(-9-$lat)/35.5*$H))];
$mapPath=static function(array $coordinates,bool $close=false)use($project):string{$parts=[];foreach($coordinates as $index=>$coordinate){[$x,$y]=$project((float)$coordinate[0],(float)$coordinate[1]);$parts[]=($index===0?'M':'L').round($x,1).' '.round($y,1);}return implode(' ',$parts).($close?' Z':'');};
$mainland=[[-22.0,113.0],[-18.2,121.0],[-14.4,126.0],[-14.7,129.0],[-12.0,135.0],[-10.8,142.0],[-15.0,145.0],[-19.0,147.0],[-24.8,153.0],[-28.2,153.5],[-32.0,151.5],[-35.8,150.0],[-38.0,147.0],[-39.0,141.0],[-37.0,137.0],[-35.0,136.0],[-33.8,128.0],[-35.0,117.0],[-32.0,115.0],[-26.0,113.0]];
$tasmania=[[-40.7,144.6],[-40.8,148.0],[-42.0,148.4],[-43.6,146.8],[-43.1,145.0]];
$stateBoundaries=[
    [[-14.7,129.0],[-31.7,129.0]],
    [[-26.0,129.0],[-26.0,141.0]],
    [[-16.2,138.0],[-26.0,138.0]],
    [[-26.0,141.0],[-34.0,141.0]],
    [[-29.0,141.0],[-29.0,148.0],[-28.2,153.4]],
    [[-34.0,141.0],[-34.2,142.0],[-35.8,144.0],[-36.0,147.0],[-37.0,150.0]],
];
$stateLabels=['WA'=>[-26.0,121.0],'NT'=>[-19.5,134.0],'SA'=>[-30.5,135.0],'QLD'=>[-22.5,146.0],'NSW'=>[-32.5,147.0],'VIC'=>[-37.2,144.0],'TAS'=>[-42.2,146.5]];
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
<section class="card"><h2>National coverage heat map</h2><p class="muted">Australia and its state boundaries are shown below. Larger, darker points have a higher opportunity score; hover or focus a point for its town, category and score.</p>
<div class="intelligence-map"><svg viewBox="0 0 <?= $W ?> <?= $H ?>" role="img" aria-labelledby="coverage-map-title coverage-map-description"><title id="coverage-map-title">Australian provider coverage opportunity heat map</title><desc id="coverage-map-description">Outline map of Australia with state and territory boundaries and town-level opportunity points.</desc>
<path class="map-land" d="<?= e_attr($mapPath($mainland,true)) ?>"/><path class="map-land" d="<?= e_attr($mapPath($tasmania,true)) ?>"/>
<?php foreach($stateBoundaries as $boundary): ?><path class="map-boundary" d="<?= e_attr($mapPath($boundary)) ?>"/><?php endforeach; ?>
<?php [$actX,$actY]=$project(-35.48,149.01); ?><circle class="map-capital-territory" cx="<?= round($actX,1) ?>" cy="<?= round($actY,1) ?>" r="5"><title>Australian Capital Territory</title></circle>
<?php foreach($stateLabels as $label=>$coordinate): [$labelX,$labelY]=$project((float)$coordinate[0],(float)$coordinate[1]); ?><text class="map-state-label" x="<?= round($labelX,1) ?>" y="<?= round($labelY,1) ?>"><?= $this->e($label) ?></text><?php endforeach; ?>
<?php foreach($points as $p): [$x,$y]=$project((float)$p['latitude'],(float)$p['longitude']);$radius=3+((float)$p['score']/100)*9; ?><circle class="heat-point" cx="<?= round($x,1) ?>" cy="<?= round($y,1) ?>" r="<?= round($radius,1) ?>" opacity="<?= .25+((float)$p['score']/100)*.65 ?>" tabindex="0"><title><?= $this->e($p['town'].', '.$p['abbreviation'].' — '.$p['category'].': opportunity score '.$p['score']) ?></title></circle><?php endforeach; ?></svg></div>
<div class="map-legend" aria-label="Heat map legend"><span><i class="map-legend-dot map-legend-dot--low"></i>Lower opportunity</span><span><i class="map-legend-dot map-legend-dot--high"></i>Higher opportunity</span><span><?= number_format(count($points)) ?> mapped town/category points</span></div></section>
<section class="card"><h2>Import quality</h2><dl class="metric-list"><div><dt>Candidates</dt><dd><?= number_format((int)($quality['total']??0)) ?></dd></div><div><dt>Awaiting review</dt><dd><?= number_format((int)($quality['pending']??0)) ?></dd></div><div><dt>Approved</dt><dd><?= number_format((int)($quality['approved']??0)) ?></dd></div><div><dt>Merged</dt><dd><?= number_format((int)($quality['merged']??0)) ?></dd></div><div><dt>Rejected</dt><dd><?= number_format((int)($quality['rejected']??0)) ?></dd></div><div><dt>Possible duplicates</dt><dd><?= number_format((int)($quality['possible_duplicates']??0)) ?></dd></div></dl><p><a href="<?= e(url('admin/data-sources/review')) ?>">Open review queue</a></p></section>
</div>

<section class="card"><div class="page-header"><div><h2>Recommended opportunities</h2><p class="muted">Score combines provider scarcity, population pressure where available, recent zero-result demand and verification coverage.</p></div></div><div class="table-wrap"><table class="data"><thead><tr><th>Priority</th><th>Location</th><th>Category</th><th>Providers</th><th>Verified</th><th>Population</th><th>Per 10k</th><th>Score</th><th>Action</th></tr></thead><tbody>
<?php foreach(array_slice($opportunities,0,100) as $row): ?><tr><td><span class="status status-<?= e_attr($row['priority']) ?>"><?= $this->e(ucfirst($row['priority'])) ?></span></td><td><?= $this->e($row['town'].', '.$row['abbreviation']) ?></td><td><?= $this->e($row['category']) ?></td><td><?= (int)$row['providers'] ?></td><td><?= $row['verification_rate']===null?'—':e((string)$row['verification_rate']).'%' ?></td><td><?= (int)$row['population']>0?number_format((int)$row['population']):'Not available' ?></td><td><?= $row['providers_per_10000']===null?'—':e((string)$row['providers_per_10000']) ?></td><td><strong><?= e((string)$row['score']) ?></strong></td><td><form method="post" action="<?= e(url('admin/data-intelligence/tasks')) ?>"><?= csrf_field() ?><input type="hidden" name="town_id" value="<?= (int)$row['town_id'] ?>"><input type="hidden" name="category_id" value="<?= (int)$row['category_id'] ?>"><input type="hidden" name="score" value="<?= e_attr((string)$row['score']) ?>"><button class="btn btn-secondary btn-sm">Send to import workflow</button></form></td></tr><?php endforeach; ?>
<?php if(!$opportunities): ?><tr><td colspan="9" class="muted">No coverage opportunities match these filters.</td></tr><?php endif; ?></tbody></table></div></section>

<section class="card"><h2>Action queue</h2><?php if(!$tasks): ?><p class="muted">No open intelligence tasks.</p><?php endif; ?><div class="grid grid-2"><?php foreach($tasks as $task): ?><article class="task-card"><div><span class="status status-<?= e_attr($task['priority']) ?>"><?= $this->e(ucfirst($task['priority'])) ?></span><h3><?= $this->e($task['title']) ?></h3><p class="muted"><?= $this->e($task['rationale']) ?></p></div><div class="btn-row"><a class="btn btn-primary btn-sm" href="<?= e(url('admin/data-sources?intelligence_task='.(int)$task['id'])) ?>">Continue import</a><form method="post" action="<?= e(url('admin/data-intelligence/tasks/status')) ?>"><?= csrf_field() ?><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><button class="btn btn-ghost btn-sm" name="status" value="completed">Complete</button></form></div></article><?php endforeach; ?></div></section>
<?php $this->endSection(); ?>
