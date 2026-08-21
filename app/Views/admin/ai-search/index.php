<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $summary */
/** @var bool $askFlagEnabled */
/** @var string $askFlagKey */
/** @var bool $datasetsFlagEnabled */
/** @var string $datasetsFlagKey */
/** @var bool $facilitiesFlagEnabled */
/** @var string $facilitiesFlagKey */
/** @var array<string,mixed> $releaseGate */
/** @var array<string,int> $retentionWindows */
/** @var array<string,mixed>|null $costSimulation */
/** @var array<string,mixed> $simInputs */
$this->extend('layouts.admin');
$settings = $summary['settings'] ?? [];
$today = $summary['today'] ?? [];
$month = $summary['month'] ?? [];
$gate = $summary['paid_ai_gate'] ?? [];
$allowlist = is_array($settings['model_allowlist'] ?? null)
    ? implode(', ', $settings['model_allowlist'])
    : '';
$datasetsFlagEnabled = $datasetsFlagEnabled ?? false;
$datasetsFlagKey = $datasetsFlagKey ?? 'assist_ai_datasets';
$facilitiesFlagEnabled = $facilitiesFlagEnabled ?? false;
$facilitiesFlagKey = $facilitiesFlagKey ?? 'assist_ai_traveller_facilities';
$releaseGate = $releaseGate ?? ['status' => 'unknown', 'checks' => []];
$retentionWindows = $retentionWindows ?? [];
$costSimulation = $costSimulation ?? null;
$simInputs = $simInputs ?? [];
?>
<?php $this->section('content'); ?>
<div class="page-header">
    <div>
        <p class="eyebrow">Platform Control Centre</p>
        <h1>Assist AI Search</h1>
        <p class="muted">Cache, budget, knowledge gaps, dataset routing and traveller facilities for the shared orchestrator. Paid AI is off by default. Structured Find a service is unchanged.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/feature-flags')) ?>">Feature flags</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/demand')) ?>">Website insights</a>
        <a class="btn btn-ghost" href="<?= e(url('admin/data-sources/datasets')) ?>">Government datasets</a>
        <a class="btn btn-secondary" href="<?= e(url('admin/ai-search/gaps')) ?>">Knowledge gaps</a>
    </div>
</div>

<section class="card">
    <h2>Today / this month</h2>
    <div class="grid grid-3">
        <div>
            <p class="muted">Requests today</p>
            <p><strong><?= (int) ($today['requests'] ?? 0) ?></strong></p>
            <p class="muted">Month: <?= (int) ($month['requests'] ?? 0) ?></p>
        </div>
        <div>
            <p class="muted">Estimated spend today (AUD)</p>
            <p><strong>$<?= number_format((float) ($today['estimated_cost_aud'] ?? 0), 4) ?></strong></p>
            <p class="muted">Month: $<?= number_format((float) ($month['estimated_cost_aud'] ?? 0), 4) ?></p>
        </div>
        <div>
            <p class="muted">Cache hit rate today</p>
            <p><strong><?= e((string) ($summary['cache_hit_rate_today_pct'] ?? 0)) ?>%</strong></p>
            <p class="muted">Rules-only <?= e((string) ($summary['rules_resolved_today_pct'] ?? 0)) ?>% · AI calls <?= e((string) ($summary['ai_required_today_pct'] ?? 0)) ?>%</p>
        </div>
    </div>
    <div class="grid grid-3" style="margin-top:1rem">
        <div>
            <p class="muted">Budget-blocked today</p>
            <p><strong><?= (int) ($today['budget_blocked'] ?? 0) ?></strong></p>
        </div>
        <div>
            <p class="muted">Failed today</p>
            <p><strong><?= (int) ($today['failed_requests'] ?? 0) ?></strong></p>
        </div>
        <div>
            <p class="muted">Paid AI gate</p>
            <p><strong><?= e((string) ($gate['state'] ?? 'ai_disabled')) ?></strong></p>
            <?php if (!empty($gate['reason'])): ?><p class="muted"><?= $this->e((string) $gate['reason']) ?></p><?php endif; ?>
        </div>
    </div>
</section>

<section class="card">
    <h2>Controls</h2>
    <form method="post" action="<?= e(url('admin/ai-search')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <label><input type="checkbox" name="assist_ai_search" value="1" <?= $askFlagEnabled ? 'checked' : '' ?>> Enable Ask VanAssist public UI (<code><?= e($askFlagKey) ?></code>)</label>
        <input type="hidden" name="assist_ai_datasets_present" value="1">
        <label><input type="checkbox" name="assist_ai_datasets" value="1" <?= $datasetsFlagEnabled ? 'checked' : '' ?>> Enable dataset routing (<code><?= e($datasetsFlagKey) ?></code>) — show staged DATA-006 candidates with provenance; never calls Google Places from Ask</label>
        <input type="hidden" name="assist_ai_traveller_facilities_present" value="1">
        <label><input type="checkbox" name="assist_ai_traveller_facilities" value="1" <?= $facilitiesFlagEnabled ? 'checked' : '' ?>> Enable traveller facilities (<code><?= e($facilitiesFlagKey) ?></code>) — toilets/dump/water etc. from <code>traveller_facilities</code> (never <code>caravan_parks</code>)</label>
        <label><input type="checkbox" name="ai_enabled" value="1" <?= !empty($settings['ai_enabled']) ? 'checked' : '' ?>> Global AI enabled (paid interpreter — keep off until approved)</label>
        <label><input type="checkbox" name="openai_enabled" value="1" <?= !empty($settings['openai_enabled']) ? 'checked' : '' ?>> OpenAI provider enabled</label>
        <label>Model allowlist (comma-separated snapshots; empty = no paid calls)
            <input type="text" name="model_allowlist" value="<?= e_attr($allowlist) ?>" placeholder="e.g. owner-approved gpt-4o-mini snapshot">
        </label>
        <p class="form-help">Recommended starting allowlist after smoke test: a pinned <code>gpt-4o-mini</code> snapshot (Structured Outputs). Evaluate <code>gpt-4.1-nano</code> only after verifying strict json_schema in your OpenAI project. See <code>docs/OPENAI_INTEGRATION.md</code>. Set <code>OPENAI_API_KEY</code> in server env — never paste keys here.</p>
        <div class="form-grid">
            <label>Daily request cap <input type="number" min="0" name="daily_request_cap" value="<?= (int) ($settings['daily_request_cap'] ?? 0) ?>"></label>
            <label>Monthly request cap <input type="number" min="0" name="monthly_request_cap" value="<?= (int) ($settings['monthly_request_cap'] ?? 0) ?>"></label>
            <label>Daily budget AUD <input type="number" min="0" step="0.01" name="daily_budget_aud" value="<?= e((string) ($settings['daily_budget_aud'] ?? '0')) ?>"></label>
            <label>Monthly budget AUD <input type="number" min="0" step="0.01" name="monthly_budget_aud" value="<?= e((string) ($settings['monthly_budget_aud'] ?? '0')) ?>"></label>
            <label>Soft warn % <input type="number" min="1" max="100" name="soft_warn_pct" value="<?= (int) ($settings['soft_warn_pct'] ?? 80) ?>"></label>
            <label>Intent cache TTL (hours) <input type="number" min="1" name="intent_cache_ttl_hours" value="<?= (int) ($settings['intent_cache_ttl_hours'] ?? 168) ?>"></label>
            <label>Max prompt chars <input type="number" min="100" name="max_prompt_chars" value="<?= (int) ($settings['max_prompt_chars'] ?? 2000) ?>"></label>
            <label>Max output tokens <input type="number" min="32" name="max_output_tokens" value="<?= (int) ($settings['max_output_tokens'] ?? 500) ?>"></label>
            <label>Max retries <input type="number" min="0" max="5" name="max_retries" value="<?= (int) ($settings['max_retries'] ?? 1) ?>"></label>
            <label>Timeout seconds <input type="number" min="1" max="120" name="timeout_seconds" value="<?= (int) ($settings['timeout_seconds'] ?? 15) ?>"></label>
        </div>
        <p class="form-help">Zero caps mean “no paid AI budget configured” — paid attempts stay blocked until non-zero caps and enable flags are set. No automatic model upgrade. API keys are never stored here.</p>
        <button class="btn btn-primary" type="submit">Save AI settings</button>
    </form>
</section>

<section class="card">
    <h2>Release gate (AI-7 + DATA-012)</h2>
    <p class="muted">Ops checklist only — does not replace Platform Quality Gate or authorise production by itself. Status: <strong><?= e((string) ($releaseGate['status'] ?? 'unknown')) ?></strong></p>
    <ul>
        <?php foreach (($releaseGate['checks'] ?? []) as $check): ?>
            <li>
                <?= !empty($check['ok']) ? '✓' : '✗' ?>
                <code><?= e((string) ($check['id'] ?? '')) ?></code>
                — <?= $this->e((string) ($check['detail'] ?? '')) ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <p class="muted">Retention windows: assist_searches <?= (int) ($retentionWindows['assist_searches_days'] ?? 180) ?>d · usage events <?= (int) ($retentionWindows['usage_events_days'] ?? 180) ?>d · gap events <?= (int) ($retentionWindows['gap_events_days'] ?? 365) ?>d. Cron task: <code>ai_retention</code>.</p>
</section>

<section class="card">
    <h2>Cost simulator</h2>
    <p class="muted">What-if estimate only. Does not call OpenAI. Rules/cache hits are $0.</p>
    <form method="get" action="<?= e(url('admin/ai-search')) ?>" class="form-stack">
        <input type="hidden" name="sim" value="1">
        <div class="form-grid">
            <label>Model <input type="text" name="sim_model" value="<?= e_attr((string) ($simInputs['model'] ?? '')) ?>" placeholder="allowlisted snapshot or gpt-4o-mini"></label>
            <label>Searches / day <input type="number" min="0" name="sim_searches_per_day" value="<?= (int) ($simInputs['searches_per_day'] ?? 100) ?>"></label>
            <label>AI hit % <input type="number" min="0" max="100" step="0.1" name="sim_ai_hit_pct" value="<?= e((string) ($simInputs['ai_hit_pct'] ?? 20)) ?>"></label>
            <label>Est. input tokens <input type="number" min="1" name="sim_input_tokens" value="<?= (int) ($simInputs['input_tokens'] ?? 800) ?>"></label>
            <label>Max output tokens <input type="number" min="32" name="sim_output_tokens" value="<?= (int) ($simInputs['output_tokens'] ?? 500) ?>"></label>
        </div>
        <button class="btn btn-secondary" type="submit">Simulate</button>
    </form>
    <?php if (!empty($costSimulation)): ?>
        <div class="grid grid-3" style="margin-top:1rem">
            <div>
                <p class="muted">Cost / AI call (AUD)</p>
                <p><strong>$<?= number_format((float) $costSimulation['cost_per_ai_call_aud'], 6) ?></strong></p>
            </div>
            <div>
                <p class="muted">Daily estimate</p>
                <p><strong>$<?= number_format((float) $costSimulation['daily_aud'], 4) ?></strong></p>
                <p class="muted"><?= (int) $costSimulation['daily_ai_calls'] ?> paid calls</p>
            </div>
            <div>
                <p class="muted">Monthly (~30d)</p>
                <p><strong>$<?= number_format((float) $costSimulation['monthly_aud'], 4) ?></strong></p>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php $this->endSection(); ?>
