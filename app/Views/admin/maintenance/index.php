<?php
/** @var \App\Core\View $this */
/** @var array<int,string> $pending */
/** @var array<int,string> $emailMissing */
/** @var array<int,string> $emailDrift */
/** @var int $emailTotal */
/** @var int $townCount */
/** @var int $townSeedTotal */
/** @var int $osmTotal */
/** @var int $localityTotal */
/** @var int $unclaimedCount */
/** @var array{seed:int,total:int,with_body:int} $pageStatus */
/** @var array<int,array<string,mixed>> $majorCityCoverage */
/** @var array{totals:array<string,int|float>,by_state:list<array<string,int|string>>,thin_sample:list<array<string,mixed>>}|null $townCoverage */
/** @var array{total:int,osm:int,locality:int,national:int,other:int,unclaimed:int,claimed:int} $providerSources */
$this->extend('layouts.admin');

$pending = $pending ?? [];
$emailMissing = $emailMissing ?? [];
$emailDrift = $emailDrift ?? [];
$townCount = (int) ($townCount ?? 0);
$townSeedTotal = (int) ($townSeedTotal ?? 0);
$osmTotal = (int) ($osmTotal ?? 0);
$localityTotal = (int) ($localityTotal ?? 0);
$unclaimedCount = (int) ($unclaimedCount ?? 0);
$pageStatus = $pageStatus ?? ['seed' => 0, 'total' => 0, 'with_body' => 0];
$providerSources = $providerSources ?? [
    'total' => 0, 'osm' => 0, 'locality' => 0, 'national' => 0, 'other' => 0, 'unclaimed' => 0, 'claimed' => 0,
];
$townCoverage = $townCoverage ?? null;
$tc = is_array($townCoverage) ? ($townCoverage['totals'] ?? []) : [];
$tcTowns = (int) ($tc['towns'] ?? 0);
$tcLocalZero = (int) ($tc['local_zero'] ?? 0);
$tcLocalThin = (int) ($tc['local_thin'] ?? 0);
$tcLocalOk = (int) ($tc['local_ok'] ?? 0);
$tcServingCovered = (int) ($tc['serving_covered'] ?? 0);
$tcPctLocal = (float) ($tc['pct_local_covered'] ?? 0);
$tcPctServing = (float) ($tc['pct_serving_covered'] ?? 0);

$needsMigrations = $pending !== [];
$needsTowns = $townSeedTotal > 0 && $townCount < max(1000, (int) ($townSeedTotal * 0.5));
$needsPages = (int) ($pageStatus['seed'] ?? 0) > 0
    && (int) ($pageStatus['with_body'] ?? 0) < (int) $pageStatus['seed'];
$needsEmails = $emailMissing !== [];
$needsEmailSync = in_array('provider_claim_invite', $emailDrift, true);
$lowCoverage = array_values(array_filter(
    $majorCityCoverage ?? [],
    static fn (array $row): bool => (int) ($row['provider_count'] ?? 0) < (int) ($row['target'] ?? 5)
));
$needsCoverage = $lowCoverage !== [];
$hasAttention = $needsMigrations || $needsTowns || $needsPages || $needsEmails || $needsEmailSync || $needsCoverage;
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin-top:0">Maintenance</h1>
    <p class="muted" style="margin-bottom:.75rem">
        Keep the live directory and site content up to date. Tip: take a
        <a href="<?= e(url('admin/backups')) ?>">backup</a> first.
    </p>
    <p style="margin:0;font-size:.95rem">
        <span class="muted">Providers</span> <?= number_format((int) $providerSources['total']) ?>
        (OSM <?= number_format((int) $providerSources['osm']) ?>
        · national <?= number_format((int) $providerSources['national']) ?>
        · locality <?= number_format((int) $providerSources['locality']) ?>
        <?php if ((int) $providerSources['other'] > 0): ?>
            · other <?= number_format((int) $providerSources['other']) ?>
        <?php endif; ?>
        · unclaimed <?= number_format((int) $providerSources['unclaimed']) ?>)
        · <span class="muted">Towns</span> <?= number_format($townCount) ?><?php if ($townSeedTotal > 0): ?>/<?= number_format($townSeedTotal) ?><?php endif; ?>
        · <span class="muted">OSM seed</span> <?= number_format($osmTotal) ?>
        <?php if ($localityTotal > 0): ?>
            · <span class="muted">Locality assignments</span> <?= number_format($localityTotal) ?>
        <?php endif; ?>
        · <span class="muted">Schema</span> <?= $needsMigrations ? count($pending) . ' pending' : 'up to date' ?>
    </p>
    <p class="muted" style="margin:.5rem 0 0;font-size:.9rem">
        ~<?= number_format((int) $providerSources['total']) ?> unique businesses is the current full directory
        (OSM + researched national + Excel locality). Locality “assignments” are town links, not extra businesses.
        To grow further: refresh with live OSM scan, or add a Google Places API key for denser per-suburb search.
    </p>
</div>

<?php if ($tcTowns > 0): ?>
<div class="card" style="margin-top:1rem">
    <h2 style="margin-top:0">Town coverage</h2>
    <p class="muted" style="margin:0 0 .75rem">
        <strong>Local</strong> = providers based in that town.
        <strong>Serving</strong> = also reached via region/state/town service areas (directory breadth).
    </p>
    <p style="margin:0 0 .75rem;font-size:.95rem">
        <?= number_format($tcTowns) ?> towns —
        local covered <?= $this->e((string) $tcPctLocal) ?>%
        (zero <?= number_format($tcLocalZero) ?>
        · thin 1–2 <?= number_format($tcLocalThin) ?>
        · 3+ <?= number_format($tcLocalOk) ?>)
        · serving covered <?= $this->e((string) $tcPctServing) ?>%
        (<?= number_format($tcServingCovered) ?> towns)
    </p>
    <?php if (is_array($townCoverage) && !empty($townCoverage['by_state'])): ?>
    <div style="overflow-x:auto">
        <table style="width:100%;border-collapse:collapse;font-size:.9rem">
            <thead>
                <tr style="text-align:left;border-bottom:1px solid #ddd">
                    <th style="padding:.35rem .5rem">State</th>
                    <th style="padding:.35rem .5rem">Towns</th>
                    <th style="padding:.35rem .5rem">Local 0</th>
                    <th style="padding:.35rem .5rem">Local 1–2</th>
                    <th style="padding:.35rem .5rem">Local 3+</th>
                    <th style="padding:.35rem .5rem">Serving</th>
                    <th style="padding:.35rem .5rem">No serving</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($townCoverage['by_state'] as $row): ?>
                <tr style="border-bottom:1px solid #eee">
                    <td style="padding:.35rem .5rem"><?= $this->e((string) $row['state']) ?></td>
                    <td style="padding:.35rem .5rem"><?= number_format((int) $row['towns']) ?></td>
                    <td style="padding:.35rem .5rem"><?= number_format((int) $row['local_zero']) ?></td>
                    <td style="padding:.35rem .5rem"><?= number_format((int) $row['local_thin']) ?></td>
                    <td style="padding:.35rem .5rem"><?= number_format((int) $row['local_ok']) ?></td>
                    <td style="padding:.35rem .5rem"><?= number_format((int) $row['serving_covered']) ?></td>
                    <td style="padding:.35rem .5rem"><?= number_format((int) $row['serving_zero']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="muted" style="margin:.75rem 0 0;font-size:.85rem">
        CLI: <code>php scripts/coverage-report.php</code>
        · thin queue: <code>php scripts/coverage-report.php --thin 200</code>
        · after widening OSM: <code>node tools/osm-import.js</code> then refresh providers below.
    </p>
</div>
<?php endif; ?>

<?php if ($hasAttention): ?>
<div class="card" style="margin-top:1rem;border-left:4px solid #e67e22">
    <h2 style="margin-top:0">Needs attention</h2>

    <?php if ($needsMigrations): ?>
        <div style="margin:0 0 1.25rem">
            <h3 style="margin:.25rem 0;font-size:1rem">Database updates</h3>
            <p class="muted" style="margin:.25rem 0"><?= count($pending) ?> pending migration(s):
                <code><?= $this->e(implode(', ', $pending)) ?></code>
            </p>
            <form method="post" action="<?= e(url('admin/maintenance/migrate')) ?>" style="margin:.5rem 0 0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Apply database updates</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($needsTowns): ?>
        <div style="margin:0 0 1.25rem">
            <h3 style="margin:.25rem 0;font-size:1rem">Australian towns</h3>
            <p class="muted" style="margin:.25rem 0">
                Only <?= number_format($townCount) ?> of <?= number_format($townSeedTotal) ?> localities are loaded.
                Suburb and postcode search needs the full set.
            </p>
            <form method="post" action="<?= e(url('admin/maintenance/seed-towns')) ?>" style="margin:.5rem 0 0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Import all Australian towns</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($needsCoverage): ?>
        <div style="margin:0 0 1.25rem">
            <h3 style="margin:.25rem 0;font-size:1rem">Major city coverage</h3>
            <p class="muted" style="margin:.25rem 0">
                <?= count($lowCoverage) ?> area(s) below target —
                <?= $this->e(implode(', ', array_map(
                    static fn (array $r): string => $r['name'] . ' (' . (int) $r['provider_count'] . ')',
                    array_slice($lowCoverage, 0, 5)
                ))) ?><?= count($lowCoverage) > 5 ? '…' : '' ?>.
            </p>
            <p class="muted" style="margin:.25rem 0">Refresh providers below, then promote cities for search/filters.</p>
            <form method="post" action="<?= e(url('admin/maintenance/feature-major-cities')) ?>" style="margin:.5rem 0 0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Promote major cities in search</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($needsPages): ?>
        <div style="margin:0 0 1.25rem">
            <h3 style="margin:.25rem 0;font-size:1rem">Website pages</h3>
            <p class="muted" style="margin:.25rem 0">
                <?= (int) $pageStatus['with_body'] ?> of <?= (int) $pageStatus['seed'] ?> standard pages have body content.
            </p>
            <form method="post" action="<?= e(url('admin/maintenance/seed-content')) ?>" style="margin:.5rem 0 0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Populate Pages &amp; Blocks</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($needsEmails): ?>
        <div style="margin:0 0 1.25rem">
            <h3 style="margin:.25rem 0;font-size:1rem">Email templates</h3>
            <p class="muted" style="margin:.25rem 0">
                Missing <?= count($emailMissing) ?> of <?= (int) $emailTotal ?>:
                <code><?= $this->e(implode(', ', $emailMissing)) ?></code>
            </p>
            <form method="post" action="<?= e(url('admin/maintenance/seed-emails')) ?>" style="margin:.5rem 0 0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-primary">Populate missing email templates</button>
            </form>
        </div>
    <?php endif; ?>

    <?php if ($needsEmailSync): ?>
        <div style="margin:0">
            <h3 style="margin:.25rem 0;font-size:1rem">Claim invite email</h3>
            <p class="muted" style="margin:.25rem 0">
                <code>provider_claim_invite</code> differs from the bundled seed.
            </p>
            <form method="post" action="<?= e(url('admin/maintenance/sync-email-template')) ?>" style="margin:.5rem 0 0">
                <?= csrf_field() ?>
                <input type="hidden" name="template_key" value="provider_claim_invite">
                <button type="submit" class="btn btn-secondary">Sync claim invite from seed</button>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card" style="margin-top:1rem;border:2px solid #0f6e6e">
    <h2 style="margin-top:0">Refresh providers</h2>
    <p class="muted">
        Loads the OpenStreetMap dataset on this server into the live directory
        <?php if ($localityTotal > 0): ?>(plus locality research) <?php endif; ?>
        and promotes major cities. Opens a Working… page that advances automatically.
    </p>
    <?php if ($osmTotal === 0): ?>
        <p style="margin:.5rem 0;padding:.75rem;border-left:4px solid #e67e22;background:#fef9f0">
            No OSM dataset is on the server yet. Tick live-scan below, or deploy <code>businesses_osm.json</code> first.
        </p>
    <?php endif; ?>
    <form method="post" action="<?= e(url('admin/maintenance/seed-providers')) ?>" style="margin:0"
          onsubmit="var b=this.querySelector('[type=submit]'); if(b){ b.disabled=true; b.textContent='Starting…'; }">
        <?= csrf_field() ?>
        <label style="display:flex;gap:.5rem;align-items:flex-start;margin:0 0 .75rem;font-size:.95rem">
            <input type="checkbox" name="scan_osm" value="1" style="margin-top:.2rem"<?= $osmTotal === 0 ? ' checked' : '' ?>>
            <span>Live-scan OpenStreetMap first (slow; needs outbound HTTPS — often blocked on shared hosting)</span>
        </label>
        <button type="submit" class="btn btn-primary">Refresh providers (auto)</button>
    </form>
    <p class="muted" style="margin:.5rem 0 0">
        Safe to re-run. See results in
        <a href="<?= e(url('admin/providers?source=unclaimed')) ?>">Providers</a>.
    </p>
</div>

<details class="card" style="margin-top:1rem">
    <summary style="cursor:pointer;font-weight:600">Advanced / one-off tools</summary>
    <div style="margin-top:1rem;display:grid;gap:1rem">
        <div>
            <h3 style="margin:0 0 .35rem;font-size:1rem">National researched import</h3>
            <p class="muted" style="margin:0 0 .5rem">Backfill matches from <code>national_import.json</code> without touching OSM.</p>
            <form method="post" action="<?= e(url('admin/maintenance/reimport')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Run national import / backfill matches</button>
            </form>
        </div>
        <?php if (!$needsTowns): ?>
        <div>
            <h3 style="margin:0 0 .35rem;font-size:1rem">Re-import towns</h3>
            <p class="muted" style="margin:0 0 .5rem">Already loaded (<?= number_format($townCount) ?>). Safe to run again — existing rows are not overwritten.</p>
            <form method="post" action="<?= e(url('admin/maintenance/seed-towns')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Import all Australian towns</button>
            </form>
        </div>
        <?php endif; ?>
        <?php if (!$needsCoverage): ?>
        <div>
            <h3 style="margin:0 0 .35rem;font-size:1rem">Promote major cities</h3>
            <p class="muted" style="margin:0 0 .5rem">All tracked cities meet the minimum provider target.</p>
            <form method="post" action="<?= e(url('admin/maintenance/feature-major-cities')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Promote major cities in search</button>
            </form>
        </div>
        <?php endif; ?>
        <?php if (!$needsPages): ?>
        <div>
            <h3 style="margin:0 0 .35rem;font-size:1rem">Re-populate pages</h3>
            <p class="muted" style="margin:0 0 .5rem">Overwrites standard page defaults. Custom pages are left alone.</p>
            <form method="post" action="<?= e(url('admin/maintenance/seed-content')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Populate Pages &amp; Blocks</button>
            </form>
        </div>
        <?php endif; ?>
        <?php if (!$needsEmails): ?>
        <div>
            <h3 style="margin:0 0 .35rem;font-size:1rem">Email templates</h3>
            <p class="muted" style="margin:0 0 .5rem">
                All <?= (int) $emailTotal ?> templates present.
                <?php if ($emailDrift !== [] && !$needsEmailSync): ?>
                    Drift (non-critical): <code><?= $this->e(implode(', ', $emailDrift)) ?></code>.
                <?php endif; ?>
                Edit under <a href="<?= e(url('admin/email-templates')) ?>">Email templates</a>.
            </p>
            <form method="post" action="<?= e(url('admin/maintenance/seed-emails')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Populate missing email templates</button>
            </form>
        </div>
        <?php endif; ?>
        <?php if (!$needsMigrations): ?>
        <div>
            <h3 style="margin:0 0 .35rem;font-size:1rem">Database updates</h3>
            <p class="muted" style="margin:0 0 .5rem">Schema is up to date.</p>
            <form method="post" action="<?= e(url('admin/maintenance/migrate')) ?>" style="margin:0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-secondary">Apply database updates</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</details>
<?php $this->endSection(); ?>
