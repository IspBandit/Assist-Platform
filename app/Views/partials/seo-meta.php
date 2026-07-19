<?php
/**
 * @var \App\Core\View $this
 * Centralised <head> SEO metadata. All inputs are optional so existing views
 * keep working. Controllers may pass: $title, $metaDescription, $canonical,
 * $metaRobots, $noindex, $ogTitle, $ogDescription, $ogImage, $ogType,
 * $jsonLd (string|array of raw JSON), and $page (CMS row with seo_* fields).
 */
use App\Services\Settings;

$page = $page ?? null;
$seoBrand = current_brand();
$seoBrandMeta = $seoBrand->metadata();

$siteName = (string) Settings::get('site_name', $seoBrand->name());

// Title: prefer an explicit page seo_title, then $title; append the site name
// once (avoid double-suffixing titles that already include it).
$rawTitle = $page['seo_title'] ?? ($title ?? $siteName);
$rawTitle = trim((string) $rawTitle);
if ($rawTitle === '') {
    $rawTitle = $siteName;
}
$fullTitle = stripos($rawTitle, $siteName) !== false ? $rawTitle : ($rawTitle . ' — ' . $siteName);

$description = $page['seo_description'] ?? ($metaDescription ?? Settings::get(
    'seo_default_description',
    $seoBrandMeta['description'] ?? ''
));
$description = trim((string) $description);

$canonicalUrl = $page['canonical_url'] ?? ($canonical ?? null);

// Indexing: a single site switch (default on only for the public launch mode),
// overridable per page via noindex. Always honour an explicit $metaRobots.
$allowIndex = (string) Settings::get('seo_allow_indexing', Settings::launchMode() === 'public' ? '1' : '0') === '1';
$pageNoindex = !empty($noindex) || !empty($page['noindex']);
$robots = $metaRobots ?? ((!$allowIndex || $pageNoindex) ? 'noindex, nofollow' : 'index, follow');

$ogTitleVal = $ogTitle ?? ($page['og_title'] ?? $fullTitle);
$ogDescVal = $ogDescription ?? ($page['og_description'] ?? $description);
$ogImageVal = $ogImage ?? ($page['og_image'] ?? Settings::get('seo_og_image', $seoBrandMeta['social_image'] ?? ''));
$ogTypeVal = $ogType ?? 'website';
$requestPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
$currentUrl = $canonicalUrl ?? ($seoBrand->url() . '/' . ltrim($requestPath, '/'));

$jsonBlocks = [];
if (!empty($jsonLd)) {
    foreach ((array) $jsonLd as $block) {
        if (is_string($block) && trim($block) !== '') {
            $jsonBlocks[] = $block;
        }
    }
}
if ($page !== null && !empty($page['schema_json'])) {
    $jsonBlocks[] = (string) $page['schema_json'];
}
?>
<title><?= $this->e($fullTitle) ?></title>
<meta name="description" content="<?= $this->e($description) ?>">
<meta name="robots" content="<?= $this->e($robots) ?>">
<?php if (!empty($canonicalUrl)): ?>
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<?php endif; ?>
<meta property="og:site_name" content="<?= $this->e($siteName) ?>">
<meta property="og:type" content="<?= $this->e($ogTypeVal) ?>">
<meta property="og:title" content="<?= $this->e($ogTitleVal) ?>">
<meta property="og:description" content="<?= $this->e($ogDescVal) ?>">
<meta property="og:url" content="<?= e($currentUrl) ?>">
<?php if (!empty($ogImageVal)): ?>
<meta property="og:image" content="<?= e($ogImageVal) ?>">
<meta name="twitter:card" content="summary_large_image">
<?php else: ?>
<meta name="twitter:card" content="summary">
<?php endif; ?>
<meta name="twitter:title" content="<?= $this->e($ogTitleVal) ?>">
<meta name="twitter:description" content="<?= $this->e($ogDescVal) ?>">
<?php foreach ($jsonBlocks as $block): ?>
<script type="application/ld+json"><?= $block /* trusted: built server-side or validated admin JSON */ ?></script>
<?php endforeach; ?>
