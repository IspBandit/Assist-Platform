<?php

declare(strict_types=1);

$brandTheme = current_brand()->theme();
?>
<style>
    :root {
        --brand-primary: <?= e($brandTheme['brand'] ?? '#0f6e6e') ?>;
        --brand-primary-emphasis: <?= e($brandTheme['brand_emphasis'] ?? '#0b5757') ?>;
        --brand-accent: <?= e($brandTheme['accent'] ?? '#b45309') ?>;
        --brand-surface: <?= e($brandTheme['surface'] ?? '#fbf8f1') ?>;
        --brand-text: <?= e($brandTheme['text'] ?? '#2b2f33') ?>;
        --brand-focus: <?= e($brandTheme['focus'] ?? '#b45309') ?>;

        /* Official Assist Platform semantic design tokens. */
        --color-brand: var(--brand-primary);
        --color-brand-emphasis: var(--brand-primary-emphasis);
        --color-accent: var(--brand-accent);
        --color-surface: var(--brand-surface);
        --color-text: var(--brand-text);
        --color-focus: var(--brand-focus);

        /* Compatibility aliases while the existing VanAssist stylesheet is
           migrated component by component to semantic tokens. */
        --teal: var(--brand-primary);
        --teal-dark: var(--brand-primary-emphasis);
        --amber: var(--brand-accent);
        --cream: var(--brand-surface);
        --charcoal: var(--brand-text);
    }
</style>
