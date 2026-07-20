<?php
declare(strict_types=1);
/** @var \App\Platform\Brand\Brand $brand */
$meta = $brand->metadata(); $theme = $brand->theme();
$destination = $brand->id() === 'towwise' ? '/tools' : '/marketplace';
$action = $brand->id() === 'towwise' ? 'Check a towing combination' : 'Browse trailer listings';
?>
<!doctype html><html lang="en-AU"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= e($brand->name()) ?> | <?= e($meta['tagline'] ?? '') ?></title><meta name="description" content="<?= e($meta['description'] ?? '') ?>">
<style>:root{--b:<?= e($theme['brand'] ?? '#334155') ?>;--s:<?= e($theme['surface'] ?? '#f8fafc') ?>;--t:<?= e($theme['text'] ?? '#111827') ?>}*{box-sizing:border-box}body{margin:0;font:18px/1.55 system-ui,sans-serif;color:var(--t);background:var(--s)}main{min-height:100vh;display:grid;place-items:center;padding:2rem}.card{max-width:52rem;background:#fff;padding:clamp(2rem,7vw,5rem);border-radius:1rem;border-top:.5rem solid var(--b);box-shadow:0 1rem 3rem #0002}h1{font-size:clamp(3rem,10vw,6rem);line-height:1;margin:0 0 1rem}p{max-width:40rem}.button{display:inline-block;margin-top:1rem;padding:.9rem 1.25rem;border-radius:.6rem;background:var(--b);color:#fff;text-decoration:none;font-weight:700}.button:focus{outline:4px solid #f59e0b;outline-offset:3px}</style></head>
<body><main><section class="card"><h1><?= e($brand->name()) ?></h1><p><strong><?= e($meta['tagline'] ?? '') ?></strong></p><p><?= e($meta['description'] ?? '') ?></p><a class="button" href="<?= e(url($destination)) ?>"><?= e($action) ?></a></section></main></body></html>
