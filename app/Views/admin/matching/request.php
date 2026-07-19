<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed> $request */
/** @var array<int,array<string,mixed>> $matches */
/** @var array<int,array<string,mixed>> $suggestions */
/** @var array<string,string> $labels */
$this->extend('layouts.admin');
$r = $request;
$id = (int) $r['id'];
?>
<?php $this->section('content'); ?>
<div class="card">
    <a class="muted" href="<?= e(url('admin/matching')) ?>">&laquo; Back to matching console</a>
    <div class="btn-row" style="justify-content:space-between;align-items:center;margin-top:.25rem">
        <h1 style="margin:0"><?= $this->e((string) $r['title']) ?></h1>
        <a class="btn btn-ghost" href="<?= e(url('admin/requests/show?id=' . $id)) ?>">Full request</a>
    </div>
    <p class="muted">
        <strong><?= $this->e((string) $r['reference']) ?></strong> ·
        <?= $this->e((string) ($r['town_name'] ?? '—')) ?> ·
        <?= $this->e((string) ($r['category_name'] ?? '—')) ?> ·
        <?= $this->e(ucfirst((string) $r['urgency'])) ?> ·
        <span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span>
    </p>
    <?php if ($r['description']): ?><p><?= nl2br($this->e((string) $r['description'])) ?></p><?php endif; ?>
</div>

<div class="card">
    <h2>Current matches</h2>
    <?php if ($matches === []): ?>
        <p class="muted">No providers matched yet. Add some from the suggestions below.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Provider</th><th>Score</th><th>Status</th><th>Contact</th><th>Update</th></tr></thead>
                <tbody>
                <?php foreach ($matches as $m): ?>
                    <tr>
                        <td>
                            <strong><?= $this->e((string) $m['business_name']) ?></strong><?= $m['is_verified'] ? ' <span class="badge badge-verified">✓</span>' : '' ?>
                            <?php if (!empty($m['auto_invited'])): ?> <span class="badge badge-neutral" title="Invited automatically">Auto</span><?php endif; ?>
                            <?php if (!empty($m['match_reasons'])): ?><div class="muted" style="font-size:.8rem"><?= $this->e((string) $m['match_reasons']) ?></div><?php endif; ?>
                        </td>
                        <td><?= $m['match_score'] !== null ? (int) $m['match_score'] : '—' ?></td>
                        <td><span class="badge badge-neutral"><?= $this->e($labels[$m['status']] ?? (string) $m['status']) ?></span></td>
                        <td>
                            <?php if ($m['contact_released']): ?>
                                <span class="badge badge-verified">Released</span>
                            <?php else: ?>
                                <form method="post" action="<?= e(url('admin/matching/release')) ?>" style="margin:0">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $id ?>">
                                    <input type="hidden" name="match_id" value="<?= (int) $m['id'] ?>">
                                    <button type="submit" class="btn btn-ghost">Release contact</button>
                                </form>
                            <?php endif; ?>
                        </td>
                        <td>
                            <form method="post" action="<?= e(url('admin/matching/update')) ?>" class="btn-row" style="margin:0">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= $id ?>">
                                <input type="hidden" name="match_id" value="<?= (int) $m['id'] ?>">
                                <select name="status">
                                    <?php foreach ($labels as $value => $label): ?>
                                        <option value="<?= e($value) ?>" <?= $m['status'] === $value ? 'selected' : '' ?>><?= $this->e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-ghost">Save</button>
                            </form>
                            <?php if ($m['provider_note']): ?><div class="muted" style="font-size:.85rem;margin-top:.25rem">Provider: <?= $this->e((string) $m['provider_note']) ?></div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h2>Suggested providers</h2>
    <p class="muted">Scored on service match, location, service model and trust signals. Already-matched providers are flagged.</p>
    <?php if ($suggestions === []): ?>
        <p class="muted">No active providers currently match this request's services or area.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Provider</th><th>Score</th><th>Why</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($suggestions as $s): ?>
                    <tr>
                        <td><strong><?= $this->e((string) $s['business_name']) ?></strong><?= $s['is_verified'] ? ' <span class="badge badge-verified">✓</span>' : '' ?>
                            <?php if (!empty($s['is_unclaimed'])): ?> <span class="badge badge-neutral" title="Unclaimed listing — auto-invite disabled">Unclaimed</span><?php endif; ?>
                            <?php if (!empty($s['auto_invite_opt_out'])): ?> <span class="badge badge-neutral">Manual contact only</span><?php endif; ?>
                        </td>
                        <td><strong><?= (int) $s['score'] ?></strong></td>
                        <td class="muted" style="font-size:.85rem"><?= $this->e(implode(' · ', $s['reasons'])) ?></td>
                        <td>
                            <?php if (!empty($s['already_matched'])): ?>
                                <span class="badge badge-neutral">Already matched</span>
                            <?php else: ?>
                                <div class="btn-row" style="margin:0">
                                    <form method="post" action="<?= e(url('admin/matching/add')) ?>" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <input type="hidden" name="provider_id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="score" value="<?= (int) $s['score'] ?>">
                                        <button type="submit" class="btn btn-ghost">Add</button>
                                    </form>
                                    <form method="post" action="<?= e(url('admin/matching/add')) ?>" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="id" value="<?= $id ?>">
                                        <input type="hidden" name="provider_id" value="<?= (int) $s['id'] ?>">
                                        <input type="hidden" name="score" value="<?= (int) $s['score'] ?>">
                                        <input type="hidden" name="invite" value="1">
                                        <button type="submit" class="btn btn-secondary">Add &amp; invite</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
