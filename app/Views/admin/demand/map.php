<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $mapData */
/** @var string $range */
/** @var string $from */
/** @var string $to */
/** @var string $rangeLabel */
$this->extend('layouts.admin');

// Server-rendered SVG scatter over an Australia outline with state borders (no JS, CSP-safe).
$W = 760; $H = 620;
$latN = -9.0; $latS = -44.5; $lngW = 112.0; $lngE = 154.5;
$project = static function (float $lat, float $lng) use ($W, $H, $latN, $latS, $lngW, $lngE): array {
    $x = ($lng - $lngW) / ($lngE - $lngW) * $W;
    $y = ($latN - $lat) / ($latN - $latS) * $H;
    return [max(0, min($W, $x)), max(0, min($H, $y))];
};
$maxNeeds = 1;
foreach (($mapData['demand'] ?? []) as $d) {
    $maxNeeds = max($maxNeeds, (int) $d['needs']);
}

$stateGeo = [];
$geoFile = base_path('database/seeds/australia_states.json');
if (is_readable($geoFile)) {
    $decoded = json_decode((string) file_get_contents($geoFile), true);
    $stateGeo = (is_array($decoded) && is_array($decoded['states'] ?? null))
        ? $decoded['states']
        : [];
}
$statePaths = [];
foreach ($stateGeo as $code => $state) {
    $parts = [];
    foreach (($state['rings'] ?? []) as $ring) {
        if (!is_array($ring) || count($ring) < 3) {
            continue;
        }
        $cmds = [];
        foreach ($ring as $i => $pt) {
            if (!is_array($pt) || count($pt) < 2) {
                continue;
            }
            [$x, $y] = $project((float) $pt[1], (float) $pt[0]);
            $cmds[] = ($i === 0 ? 'M' : 'L') . round($x, 1) . ' ' . round($y, 1);
        }
        if ($cmds !== []) {
            $parts[] = implode(' ', $cmds) . ' Z';
        }
    }
    if ($parts !== []) {
        $statePaths[] = [
            'code' => (string) $code,
            'name' => (string) ($state['name'] ?? $code),
            'd'    => implode(' ', $parts),
        ];
    }
}
?>
<?php $this->section('content'); ?>
<h1>Demand map</h1>
<?php $this->include('partials.demand-range', ['action' => url('admin/demand/map'), 'range' => $range, 'from' => $from, 'to' => $to, 'rangeLabel' => $rangeLabel]); ?>

<p class="muted">Aggregated to town level — no customer addresses.
    <span style="color:#c62828">●</span> customer demand (size = volume),
    <span style="color:#f9a825">●</span> unclaimed listings,
    <span style="color:#2e7d32">●</span> verified providers,
    <span style="color:#1565c0">■</span> claimed (not yet verified).
</p>

<div class="card" style="overflow:auto">
    <svg viewBox="0 0 <?= $W ?> <?= $H ?>" width="100%" style="max-width:<?= $W ?>px;background:#dfe8ef;border-radius:8px" role="img" aria-label="Demand map of Australia">
        <g fill="#f3f1ea" stroke="#8a8578" stroke-width="1.25" stroke-linejoin="round">
            <?php foreach ($statePaths as $sp): ?>
                <path d="<?= $this->e($sp['d']) ?>" data-state="<?= $this->e($sp['code']) ?>">
                    <title><?= $this->e($sp['name']) ?></title>
                </path>
            <?php endforeach; ?>
        </g>
        <?php // Demand under providers so coloured dots stay visible ?>
        <?php foreach (($mapData['demand'] ?? []) as $d): ?>
            <?php if ($d['lat'] === null || $d['lng'] === null) { continue; } [$x, $y] = $project((float) $d['lat'], (float) $d['lng']); $r = 3 + 12 * sqrt((int) $d['needs'] / max(1, $maxNeeds)); ?>
            <circle cx="<?= round($x, 1) ?>" cy="<?= round($y, 1) ?>" r="<?= round($r, 1) ?>" fill="#c62828" opacity="0.35"></circle>
        <?php endforeach; ?>
        <?php foreach (($mapData['providers'] ?? []) as $p): ?>
            <?php if ($p['lat'] === null || $p['lng'] === null) { continue; } [$x, $y] = $project((float) $p['lat'], (float) $p['lng']); ?>
            <rect x="<?= round($x - 3, 1) ?>" y="<?= round($y - 3, 1) ?>" width="6" height="6" fill="#1565c0" stroke="#0d47a1" stroke-width="0.6" opacity="0.9"></rect>
        <?php endforeach; ?>
        <?php foreach (($mapData['providersUnclaimed'] ?? []) as $p): ?>
            <?php if ($p['lat'] === null || $p['lng'] === null) { continue; } [$x, $y] = $project((float) $p['lat'], (float) $p['lng']); ?>
            <circle cx="<?= round($x - 3.5, 1) ?>" cy="<?= round($y, 1) ?>" r="3.5" fill="#f9a825" stroke="#f57f17" stroke-width="0.6" opacity="0.95"></circle>
        <?php endforeach; ?>
        <?php foreach (($mapData['providersVerified'] ?? []) as $p): ?>
            <?php if ($p['lat'] === null || $p['lng'] === null) { continue; } [$x, $y] = $project((float) $p['lat'], (float) $p['lng']); ?>
            <circle cx="<?= round($x + 3.5, 1) ?>" cy="<?= round($y, 1) ?>" r="3.5" fill="#2e7d32" stroke="#1b5e20" stroke-width="0.6" opacity="0.95"></circle>
        <?php endforeach; ?>
    </svg>
</div>

<div class="grid grid-2" style="margin-top:1rem">
    <div class="card">
        <h3 style="margin-top:0">Demand by town (table)</h3>
        <table class="table">
            <thead><tr><th>Town</th><th>Needs</th></tr></thead>
            <tbody>
                <?php foreach (array_slice($mapData['demand'] ?? [], 0, 50) as $d): ?>
                    <tr><td><?= $this->e((string) $d['name']) ?></td><td><?= (int) $d['needs'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (($mapData['demand'] ?? []) === []): ?><tr><td colspan="2" class="muted">No located demand yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="card">
        <h3 style="margin-top:0">Provider base towns (table)</h3>
        <table class="table">
            <thead><tr><th>Town</th><th>Unclaimed</th><th>Verified</th><th>Claimed</th></tr></thead>
            <tbody>
                <?php
                $byTown = [];
                foreach (['providersUnclaimed' => 'unclaimed', 'providersVerified' => 'verified', 'providers' => 'claimed'] as $key => $col) {
                    foreach (($mapData[$key] ?? []) as $p) {
                        $id = (int) $p['id'];
                        if (!isset($byTown[$id])) {
                            $byTown[$id] = ['name' => (string) $p['name'], 'unclaimed' => 0, 'verified' => 0, 'claimed' => 0, 'total' => 0];
                        }
                        $byTown[$id][$col] = (int) $p['providers'];
                        $byTown[$id]['total'] += (int) $p['providers'];
                    }
                }
                uasort($byTown, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);
                ?>
                <?php foreach (array_slice($byTown, 0, 50, true) as $row): ?>
                    <tr>
                        <td><?= $this->e($row['name']) ?></td>
                        <td><?= (int) $row['unclaimed'] ?></td>
                        <td><?= (int) $row['verified'] ?></td>
                        <td><?= (int) $row['claimed'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if ($byTown === []): ?><tr><td colspan="4" class="muted">No located providers yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
