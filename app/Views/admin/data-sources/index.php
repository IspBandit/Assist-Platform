<?php $this->extend('layouts.admin'); ?>
<?php $this->section('content'); ?>
<div class="page-header"><div><p class="eyebrow">Platform Control Centre</p><h1>Data sources</h1><p class="muted">Discover gaps through pluggable connectors. Nothing becomes public until it passes the review queue.</p></div><a class="btn btn-primary" href="<?= e(url('admin/data-sources/review')) ?>">Open review queue</a></div>
<?php if(!empty($intelligenceTask)): ?><div class="notice notice-info"><strong>Recommended task #<?= (int)$intelligenceTask['id'] ?>:</strong> <?= $this->e($intelligenceTask['title']) ?>. The matching category and location are preselected below.</div><?php endif; ?>

<div class="stats-grid">
<?php foreach ($connectors as $connector): ?>
<article class="stat-card"><span><?= $this->e($connector['name']) ?></span><strong><?= $this->e(ucfirst((string)$connector['status'])) ?></strong><small><?= (int)$connector['requests_today'] ?> / <?= (int)$connector['daily_request_limit'] ?> requests today · $<?= number_format((float)$connector['cost_today'],2) ?> estimated</small></article>
<?php endforeach; ?>
</div>

<div class="grid grid-2">
<?php foreach ($connectors as $connector): ?>
<section class="card"><h2><?= $this->e($connector['name']) ?></h2><p class="muted">Credential: <?= $this->e($connector['value_hint'] ?: 'Not configured') ?>. Saved values are encrypted and never displayed again.</p>
<form method="post" action="<?= e(url('admin/data-sources/connector')) ?>" class="form-stack"><?= csrf_field() ?><input type="hidden" name="connector_id" value="<?= (int)$connector['id'] ?>">
<label>New API key <input type="password" name="api_key" autocomplete="new-password" placeholder="Leave blank to keep current key"></label>
<div class="form-grid"><label>Daily request limit <input type="number" min="1" name="daily_request_limit" value="<?= (int)$connector['daily_request_limit'] ?>"></label><label>Daily budget guard (AUD) <input type="number" min="0" step="0.01" name="daily_budget_aud" value="<?= e((string)$connector['daily_budget_aud']) ?>"></label></div>
<label><input type="checkbox" name="active" value="1" <?= $connector['status']==='active'?'checked':'' ?>> Enable connector</label><button class="btn btn-primary" type="submit">Save secure settings</button></form></section>
<?php endforeach; ?>

<section class="card"><h2>Gap finder</h2><p class="muted">Run one focused query at a time to keep cost and quota use controlled.</p><form method="post" action="<?= e(url('admin/data-sources/run')) ?>" class="form-stack"><?= csrf_field() ?>
<label>Connector <select name="connector_id" required><?php foreach($connectors as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $this->e($c['name']) ?></option><?php endforeach; ?></select></label>
<?php if(!empty($intelligenceTask)): ?><input type="hidden" name="intelligence_task" value="<?= (int)$intelligenceTask['id'] ?>"><?php endif; ?>
<label>Mapped category <select name="mapping_id" required><option value="">Select mapping</option><?php foreach($mappings as $m): if(empty($m['id'])||empty($m['is_active']))continue; ?><option value="<?= (int)$m['id'] ?>" <?= !empty($intelligenceTask)&&((int)$intelligenceTask['mapping_id']===(int)$m['id'])?'selected':'' ?>><?= $this->e($m['category_name'].' — '.$m['external_query']) ?></option><?php endforeach; ?></select></label>
<label>Town, suburb, postcode or region <input name="location" required value="<?= e_attr((string)($intelligenceTask['town']??'')) ?>" placeholder="e.g. Longreach QLD"></label><button class="btn btn-primary" type="submit">Find missing providers</button></form></section>

<section class="card"><h2>Schedule an import</h2><form method="post" action="<?= e(url('admin/data-sources/schedule')) ?>" class="form-stack"><?= csrf_field() ?>
<label>Name <input name="name" required placeholder="Weekly regional mechanics gap scan"></label><label>Connector <select name="connector_id"><?php foreach($connectors as $c): ?><option value="<?= (int)$c['id'] ?>"><?= $this->e($c['name']) ?></option><?php endforeach; ?></select></label>
<label>Mapping <select name="mapping_id" required><?php foreach($mappings as $m): if(empty($m['id']))continue; ?><option value="<?= (int)$m['id'] ?>"><?= $this->e($m['category_name']) ?></option><?php endforeach; ?></select></label><label>Location <input name="location" required></label><label>Frequency <select name="frequency"><option>daily</option><option selected>weekly</option><option>monthly</option></select></label><label><input type="checkbox" name="enabled" value="1"> Enable schedule</label><button class="btn btn-secondary">Save schedule</button></form></section>
</div>

<section class="card"><div class="page-header"><div><h2>Category mappings</h2><p class="muted">Search language belongs to the connector configuration, not application code.</p></div></div><div class="table-wrap"><table class="data"><thead><tr><th>Platform category</th><th>Connector query</th><th>Status</th><th>Save</th></tr></thead><tbody>
<?php foreach($mappings as $m): ?><tr><form method="post" action="<?= e(url('admin/data-sources/mapping')) ?>"><?= csrf_field() ?><input type="hidden" name="connector_id" value="<?= (int)($connectors[0]['id']??0) ?>"><input type="hidden" name="category_id" value="<?= (int)$m['category_id'] ?>"><td><?= $this->e($m['category_name']) ?></td><td><input name="external_query" value="<?= e_attr((string)($m['external_query']??'')) ?>" placeholder="e.g. mobile mechanic"></td><td><label><input type="checkbox" name="active" value="1" <?= !isset($m['is_active'])||$m['is_active']?'checked':'' ?>> Active</label></td><td><button class="btn btn-secondary" type="submit">Save</button></td></form></tr><?php endforeach; ?>
</tbody></table></div></section>

<div class="grid grid-2"><section class="card"><h2>Coverage by category</h2><div class="table-wrap"><table class="data"><thead><tr><th>Category</th><th>Active</th><th>Verified</th></tr></thead><tbody><?php foreach($coverage as $row): ?><tr><td><?= $this->e($row['name']) ?></td><td><?= (int)$row['provider_count'] ?></td><td><?= (int)$row['verified_count'] ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="card"><h2>Recent import jobs</h2><div class="table-wrap"><table class="data"><thead><tr><th>Source</th><th>Query</th><th>Status</th><th>Found</th></tr></thead><tbody><?php foreach($jobs as $job): ?><tr><td><?= $this->e($job['connector_name']) ?></td><td><?= $this->e($job['external_query']??'') ?></td><td><?= $this->e($job['status']) ?></td><td><?= (int)$job['candidates_found'] ?></td></tr><?php endforeach; ?></tbody></table></div><h3>Schedules</h3><?php if(!$schedules): ?><p class="muted">No schedules yet.</p><?php endif; ?><?php foreach($schedules as $schedule): ?><p><strong><?= $this->e($schedule['name']) ?></strong><br><span class="muted"><?= $this->e($schedule['frequency']) ?> · <?= !empty($schedule['is_enabled'])?'enabled':'paused' ?></span></p><?php endforeach; ?></section></div>
<?php $this->endSection(); ?>
