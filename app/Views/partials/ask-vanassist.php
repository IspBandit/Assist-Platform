<?php
/** Ask VanAssist homepage form — only render when assist_ai_search is enabled. */
use App\Platform\AiSearch\Support\AiSearchFeature;

if (!AiSearchFeature::enabled() || current_brand()->id() !== 'vanassist') {
    return;
}
?>
<aside class="ask-vanassist-home ask-vanassist-home--primary" aria-labelledby="home-ask-heading">
    <div class="search-head" id="home-ask-heading">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 5h16v11H8l-4 4V5Z"/><path d="M8 9h8M8 12h5"/></svg>
        Ask VanAssist
    </div>
    <p class="ask-vanassist-intro">Tell us what has happened or what you need, and include a town, postcode or “near me”.</p>
    <form method="get" action="<?= e(url('ask')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
        <label for="home-ask-q">What do you need help finding?</label>
        <div class="ask-vanassist-home-row">
            <input type="text" id="home-ask-q" name="q" maxlength="240"
                placeholder="e.g. My caravan brakes are grinding near Emerald"
                autocomplete="off" required>
            <button type="submit" class="btn btn-primary btn-lg">Find the right help</button>
        </div>
        <input type="hidden" name="lat" value="">
        <input type="hidden" name="lng" value="">
        <div class="hp-field" aria-hidden="true">
            <label for="home-ask-website">Website</label>
            <input type="text" id="home-ask-website" name="website" value="" tabindex="-1" autocomplete="off">
        </div>
        <div class="ask-vanassist-home-foot">
            <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline', 'autoSubmit' => 'false']); ?>
            <span>Reviewed providers, stays and traveller facilities. Free to search.</span>
        </div>
        <p class="location-status muted" role="status" aria-live="polite" hidden></p>
    </form>
</aside>
