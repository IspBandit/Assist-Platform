<?php
/** @var \App\Core\View $this */
/** @var mixed $siteName */
/** @var mixed $description */
/** @var mixed $ogImage */
/** @var mixed $googleVerification */
/** @var mixed $bingVerification */
/** @var bool $allowIndex */
/** @var string $launchMode */
/** @var string $sitemapUrl */
/** @var string $robotsUrl */
$this->extend('layouts.admin');
?>
<?php $this->section('content'); ?>
<div class="card">
    <h1 style="margin:0 0 1rem">SEO settings</h1>

    <form method="post" action="<?= e(url('admin/seo')) ?>" class="stack">
        <?= csrf_field() ?>

        <div class="form-group">
            <label for="site_name">Site name</label>
            <input type="text" id="site_name" name="site_name" value="<?= e_attr((string) $siteName) ?>">
            <p class="muted" style="margin:.25rem 0 0;font-size:.85rem">Appended to page titles and used in social tags.</p>
        </div>

        <div class="form-group">
            <label for="seo_default_description">Default meta description</label>
            <textarea id="seo_default_description" name="seo_default_description" rows="3"><?= e((string) $description) ?></textarea>
        </div>

        <div class="form-group">
            <label for="seo_og_image">Default social share image (absolute URL)</label>
            <input type="text" id="seo_og_image" name="seo_og_image" value="<?= e_attr((string) $ogImage) ?>" placeholder="https://…">
        </div>

        <div class="grid grid-2">
            <div class="form-group">
                <label for="google_site_verification">Google Search Console verification token</label>
                <input type="text" id="google_site_verification" name="google_site_verification" value="<?= e_attr((string) $googleVerification) ?>" autocomplete="off">
                <p class="muted">Paste only the value from Google's HTML meta tag, not the whole tag.</p>
            </div>
            <div class="form-group">
                <label for="bing_site_verification">Bing Webmaster Tools verification token</label>
                <input type="text" id="bing_site_verification" name="bing_site_verification" value="<?= e_attr((string) $bingVerification) ?>" autocomplete="off">
                <p class="muted">Bing also supplies results to other search experiences.</p>
            </div>
        </div>

        <div class="card" style="background:#faf8f2">
            <label><input type="checkbox" name="seo_allow_indexing" value="1" <?= $allowIndex ? 'checked' : '' ?>> Allow search engines to index this site</label>
            <p class="muted" style="margin:.5rem 0 0">Current launch mode: <strong><?= $this->e($launchMode) ?></strong>. While indexing is off, every page sends <code>noindex</code> and <code>robots.txt</code> disallows crawling. Turn this on for public launch.</p>
        </div>

        <div class="btn-row"><button type="submit" class="btn btn-primary">Save settings</button></div>
    </form>
</div>

<div class="card">
    <h2 style="margin-top:0">Generated files</h2>
    <ul class="list-plain">
        <li><a href="<?= e($sitemapUrl) ?>" target="_blank" rel="noopener"><?= e($sitemapUrl) ?></a></li>
        <li><a href="<?= e($robotsUrl) ?>" target="_blank" rel="noopener"><?= e($robotsUrl) ?></a></li>
    </ul>
    <p class="muted">After verifying the site, submit the sitemap above in Google Search Console and Bing Webmaster Tools. Search engines decide when and how pages appear; this site supplies the required crawl, canonical and structured metadata.</p>
</div>
<?php $this->endSection(); ?>
