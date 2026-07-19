<?php
/** @var \App\Core\View $this */
/** @var array<int,array<string,mixed>> $duplicates */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin-top:0">Possible duplicate providers</h1>
    <p class="muted">Matched by identical phone, website or same business name + base town. Review before outreach or claiming.</p>
    <p><a href="<?= e(url('admin/providers')) ?>">&laquo; Back to providers</a></p>
</div>
<div class="card" style="margin-top:1rem">
    <?php if ($duplicates === []): ?>
        <p class="muted">No obvious duplicates found.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="data">
                <thead><tr><th>Listing A</th><th>Listing B</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($duplicates as $d): ?>
                    <tr>
                        <td>
                            <a href="<?= e(url('admin/providers/show?id=' . (int) $d['id_a'])) ?>"><?= $this->e((string) $d['name_a']) ?></a>
                            <?= !empty($d['unclaimed_a']) ? ' <span class="badge badge-neutral">Unclaimed</span>' : '' ?>
                            <?php if (!empty($d['phone_a'])): ?><div class="muted"><?= $this->e((string) $d['phone_a']) ?></div><?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= e(url('admin/providers/show?id=' . (int) $d['id_b'])) ?>"><?= $this->e((string) $d['name_b']) ?></a>
                            <?= !empty($d['unclaimed_b']) ? ' <span class="badge badge-neutral">Unclaimed</span>' : '' ?>
                            <?php if (!empty($d['phone_b'])): ?><div class="muted"><?= $this->e((string) $d['phone_b']) ?></div><?php endif; ?>
                        </td>
                        <td><a class="btn btn-ghost" href="<?= e(url('admin/providers/show?id=' . (int) $d['id_a'])) ?>">Review A</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php $this->endSection(); ?>
