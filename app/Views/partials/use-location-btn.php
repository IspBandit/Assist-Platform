<?php
/** @var string $autoSubmit 'true' to submit the form after resolving (search), 'false' to only fill fields */
/** @var string|null $selectTarget CSS selector for a <select> to set (e.g. request form town_id) */
/** @var string|null $postcodeTarget CSS selector for postcode input */
/** @var string $class Extra CSS classes */
$autoSubmit = $autoSubmit ?? 'true';
$selectTarget = $selectTarget ?? null;
$postcodeTarget = $postcodeTarget ?? null;
$class = trim('use-location ' . ($class ?? ''));
?>
<button type="button"
        class="<?= e_attr($class) ?>"
        data-use-location
        data-auto-submit="<?= e_attr($autoSubmit) ?>"
        <?php if ($selectTarget): ?>data-select-target="<?= e_attr($selectTarget) ?>"<?php endif; ?>
        <?php if ($postcodeTarget): ?>data-postcode-target="<?= e_attr($postcodeTarget) ?>"<?php endif; ?>
        hidden>
    <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 12-9 12s-9-5-9-12a9 9 0 0 1 18 0Z"/><circle cx="12" cy="10" r="3"/></svg>
    Use my current location
</button>
