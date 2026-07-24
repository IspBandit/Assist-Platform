<?php

declare(strict_types=1);

/** @var \App\Platform\Brand\Brand $brand */
$theme = $brand->theme();
$metadata = $brand->metadata();
?>
<!doctype html>
<html lang="en-AU">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($brand->name()) ?> — Coming soon</title>
    <meta name="description" content="<?= e($metadata['description'] ?? '') ?>">
    <style>
        :root {
            color-scheme: light;
            --brand: <?= e($theme['brand'] ?? '#334155') ?>;
            --brand-emphasis: <?= e($theme['brand_emphasis'] ?? '#1e293b') ?>;
            --surface: <?= e($theme['surface'] ?? '#f8fafc') ?>;
            --text: <?= e($theme['text'] ?? '#0f172a') ?>;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 1.5rem;
            color: var(--text);
            background: var(--surface);
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        main {
            width: min(42rem, 100%);
            padding: clamp(2rem, 6vw, 4rem);
            border-top: .4rem solid var(--brand);
            border-radius: .75rem;
            background: #fff;
            box-shadow: 0 1.5rem 4rem rgb(15 23 42 / 12%);
            text-align: center;
        }
        .brand {
            margin: 0 0 1rem;
            color: var(--brand-emphasis);
            font-size: clamp(2rem, 8vw, 4rem);
            line-height: 1;
        }
        .tagline { margin: 0 0 1.5rem; font-size: 1.25rem; }
        .status { margin: 0; color: #475569; }
    </style>
</head>
<body>
<main>
    <h1 class="brand"><?= e($brand->name()) ?></h1>
    <p class="tagline"><?= e($metadata['tagline'] ?? '') ?></p>
    <p class="status">This Assist Platform brand is being prepared. No production functionality is available yet.</p>
</main>
</body>
</html>
