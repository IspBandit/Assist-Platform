<?php
/** Ask VanAssist homepage form — only render when assist_ai_search is enabled. */
use App\Platform\AiSearch\Support\AiSearchFeature;

if (!AiSearchFeature::enabled() || current_brand()->id() !== 'vanassist') {
    return;
}
?>
<aside class="ask-vanassist-home">
    <div class="ask-vanassist-divider"><span>or ask in plain language</span></div>
    <form method="get" action="<?= e(url('ask')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
        <label for="home-ask-q">Ask VanAssist</label>
        <div class="ask-vanassist-home-row">
            <input type="text" id="home-ask-q" name="q" maxlength="240"
                placeholder="e.g. My caravan is making a grinding noise near Emerald"
                autocomplete="off" required>
            <button type="submit" class="btn btn-primary">Ask</button>
        </div>
        <input type="hidden" name="lat" value="">
        <input type="hidden" name="lng" value="">
        <div class="hp-field" aria-hidden="true">
            <label for="home-ask-website">Website</label>
            <input type="text" id="home-ask-website" name="website" value="" tabindex="-1" autocomplete="off">
        </div>
        <div class="ask-vanassist-home-foot">
            <?php $this->include('partials.use-location-btn', ['class' => 'use-location-inline', 'autoSubmit' => 'false']); ?>
            <span>Providers, stays and traveller facilities only</span>
        </div>
        <p class="location-status muted" role="status" aria-live="polite" hidden></p>
    </form>
</aside>
