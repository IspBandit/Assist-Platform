<?php
/** Ask VanAssist teaser — only render when assist_ai_search is enabled. */
use App\Platform\AiSearch\Support\AiSearchFeature;

if (!AiSearchFeature::enabled() || current_brand()->id() !== 'vanassist') {
    return;
}
?>
<aside class="ask-vanassist-teaser" style="margin:1rem 0 0">
    <p style="margin:0 0 0.5rem"><strong>Ask VanAssist</strong> — describe what you need in plain language.</p>
    <a class="btn btn-secondary" href="<?= e(url('ask')) ?>">Ask VanAssist</a>
</aside>
