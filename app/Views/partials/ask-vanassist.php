<?php
/** Ask VanAssist homepage form — only render when assist_ai_search is enabled. */
use App\Platform\AiSearch\Support\AiSearchFeature;

if (!AiSearchFeature::enabled() || current_brand()->id() !== 'vanassist') {
    return;
}
?>
<aside class="ask-vanassist-home" aria-labelledby="ask-vanassist-home-heading">
    <div class="ask-vanassist-divider"><span>Start here</span></div>
    <form method="get" action="<?= e(url('ask')) ?>" data-nearest-url="<?= e_attr(url('locations/nearest')) ?>">
        <label id="ask-vanassist-home-heading" for="home-ask-q">Ask VanAssist</label>
        <p class="ask-vanassist-home-intro">Tell us what you need in plain English. We can look across providers, stays and traveller facilities.</p>
        <div class="ask-vanassist-home-row">
            <input type="text" id="home-ask-q" name="q" maxlength="240"
                placeholder="e.g. Find a caravan repairer near Emerald"
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
            <span>Try: dump point, pet-friendly stay, mobile mechanic, drinking water</span>
        </div>
        <p class="location-status muted" role="status" aria-live="polite" hidden></p>
    </form>
</aside>
