<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $requests */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0">Matching console</h1>
    <p class="muted">Requests in the matching pipeline, most urgent first. Open one to see scored provider suggestions and manage invitations.</p>
</div>

<div class="card">
    <div class="table-wrap">
        <table class="data">
            <thead><tr><th>Reference</th><th>Summary</th><th>Town</th><th>Category</th><th>Urgency</th><th>Status</th><th>Matches</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($requests as $r): ?>
                <tr>
                    <td><strong><?= $this->e((string) $r['reference']) ?></strong></td>
                    <td><?= $this->e((string) $r['title']) ?></td>
                    <td><?= $this->e((string) ($r['town_name'] ?? '—')) ?></td>
                    <td><?= $this->e((string) ($r['category_name'] ?? '—')) ?></td>
                    <td><?= $this->e(ucfirst((string) $r['urgency'])) ?></td>
                    <td>
                        <span class="badge badge-neutral"><?= $this->e(\App\Services\RequestWorkflow::label((string) $r['status'])) ?></span>
                        <?php if (($r['auto_match_state'] ?? '') === 'fallback_admin'): ?><span class="badge badge-warning" title="Automation could not match this request">Needs you</span>
                        <?php elseif (($r['auto_match_state'] ?? '') === 'done'): ?><span class="badge badge-neutral" title="Auto-matched">Auto</span>
                        <?php elseif (($r['auto_match_state'] ?? '') === 'locked'): ?><span class="badge badge-confirmed" title="Contact released to the cap of providers">Locked</span><?php endif; ?>
                    </td>
                    <td><?= (int) $r['match_count'] ?><?php if ((int) $r['interested_count'] > 0): ?> <span class="badge badge-confirmed"><?= (int) $r['interested_count'] ?> interested</span><?php endif; ?></td>
                    <td><a class="btn btn-primary" href="<?= e(url('admin/matching/request?id=' . (int) $r['id'])) ?>">Match</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if ($requests === []): ?><tr><td colspan="8" class="muted">No requests are currently awaiting matching.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php $this->endSection(); ?>
