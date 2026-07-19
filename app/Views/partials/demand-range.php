<?php
/** @var \App\Core\View $this */
/** @var string $action absolute URL the form submits to (GET) */
/** @var string $range */
/** @var string $from */
/** @var string $to */
/** @var string $rangeLabel */
$ranges = ['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'fy' => 'This financial year', 'pfy' => 'Previous financial year', 'custom' => 'Custom range'];
?>
<form method="get" action="<?= e($action) ?>" class="card btn-row" style="align-items:flex-end;gap:1rem;flex-wrap:wrap">
    <label>Period
        <select name="range">
            <?php foreach ($ranges as $k => $label): ?>
                <option value="<?= e($k) ?>" <?= ($range ?? '30d') === $k ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>From <input type="date" name="from" value="<?= e_attr($from ?? '') ?>"></label>
    <label>To <input type="date" name="to" value="<?= e_attr($to ?? '') ?>"></label>
    <button type="submit" class="btn btn-secondary">Apply</button>
    <span class="muted"><?= $this->e($rangeLabel ?? '') ?></span>
</form>
