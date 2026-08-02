<?php $this->extend('layouts.public'); ?>
<?php $this->section('content'); ?>

<section class="product-hero product-hero--polaris" aria-labelledby="polaris-hero-heading">
    <div class="polaris-hero-motif" aria-hidden="true"></div>
    <div class="container product-hero-content polaris-hero-grid">
        <div class="product-hero-copy">
            <span class="product-kicker">Australia’s new RV decision platform</span>
            <h1 id="polaris-hero-heading">Find the right RV for the way you travel.</h1>
            <p>Tell us what you tow, where you go and what matters most. We’ll match you with new caravans, hybrids and campers that genuinely fit.</p>

            <form class="polaris-hero-form" method="get" action="<?= e(url('find')) ?>" data-polaris-hero-form>
                <input type="hidden" name="stage" value="1">
                <label class="sr-only" for="polaris-hero-prompt">Describe how you travel</label>
                <textarea
                    id="polaris-hero-prompt"
                    name="q"
                    rows="3"
                    required
                    placeholder="<?= e($heroExamples[0] ?? '') ?>"
                    data-polaris-examples="<?= e(implode('|', $heroExamples ?? [])) ?>"
                ></textarea>
                <div class="polaris-hero-actions">
                    <button type="submit" class="btn btn-light btn-lg" data-polaris-hero-submit>Find my matches</button>
                    <a class="btn btn-glass btn-lg" href="<?= e(url('find?stage=1')) ?>">Answer a few questions</a>
                    <a class="btn btn-glass btn-lg" href="<?= e(url('rvs')) ?>">Browse all new RVs</a>
                </div>
                <p class="muted small" data-polaris-hero-loading hidden aria-live="polite">Preparing guided matching…</p>
            </form>
            <script>
            (function () {
                var form = document.querySelector('[data-polaris-hero-form]');
                if (!form) return;
                var examples = (form.querySelector('[data-polaris-examples]')?.getAttribute('data-polaris-examples') || '').split('|').filter(Boolean);
                var ta = form.querySelector('#polaris-hero-prompt');
                var btn = form.querySelector('[data-polaris-hero-submit]');
                var loading = form.querySelector('[data-polaris-hero-loading]');
                var reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (ta && examples.length > 1 && !reduce) {
                    var i = 0;
                    setInterval(function () {
                        if (document.activeElement === ta || (ta.value && ta.value !== examples[i])) return;
                        i = (i + 1) % examples.length;
                        ta.placeholder = examples[i];
                    }, 6000);
                }
                form.addEventListener('submit', function () {
                    if (btn) { btn.disabled = true; btn.textContent = 'Finding matches…'; }
                    if (loading) loading.hidden = false;
                    try { sessionStorage.setItem('polaris_hero_q', ta ? ta.value : ''); } catch (e) {}
                });
            })();
            </script>
        </div>

        <aside class="polaris-hero-preview" aria-label="Illustrative match preview">
            <p class="polaris-preview-label">Illustrative preview</p>
            <ul>
                <li><strong>37</strong> suitable models found</li>
                <li>Top match: <strong>96%</strong></li>
                <li>Tow compatibility checked</li>
                <li>7-day off-grid capability</li>
            </ul>
            <p class="muted small">Preview only — not live catalogue statistics.</p>
        </aside>
    </div>
</section>

<section class="quick-paths" aria-label="Choose how to begin">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">Start here</span>
            <h2>Choose how to begin</h2>
        </div>
        <div class="quick-paths-grid polaris-start-grid">
            <a href="<?= e(url('find')) ?>"><span class="quick-icon">01</span><span><strong>Find by lifestyle</strong><small>Guided matching for how you travel</small></span></a>
            <a href="<?= e(url('tow-match')) ?>"><span class="quick-icon">02</span><span><strong>Match to my tow vehicle</strong><small>TowSmart-powered compatibility</small></span></a>
            <a href="<?= e(url('floorplans')) ?>"><span class="quick-icon">03</span><span><strong>Browse floorplans</strong><small>Layouts before brand names</small></span></a>
            <a href="<?= e(url('rvs')) ?>"><span class="quick-icon">04</span><span><strong>Explore all new RVs</strong><small>Current catalogue browse</small></span></a>
        </div>
    </div>
</section>

<section class="section product-section" aria-labelledby="travel-profiles-heading">
    <div class="container">
        <div class="section-heading">
            <span class="product-kicker dark">Popular travel profiles</span>
            <h2 id="travel-profiles-heading">Start from a familiar journey.</h2>
            <p>These profiles are starting points, not rigid labels.</p>
        </div>
        <div class="polaris-profile-grid">
            <?php foreach ([
                ['Couples touring', 'Two adults, sealed and gravel roads'],
                ['Family adventures', 'Bunks, storage and park stays'],
                ['Remote and off-road', 'Rough gravel with clear definitions'],
                ['Lightweight towing', 'Lower ATM and easier towability'],
                ['Extended free camping', 'Water, solar and battery focus'],
                ['Premium comfort', 'Ensuite, climate and living space'],
                ['Compact hybrids', 'Shorter bodies, flexible layouts'],
                ['Weekend escapes', 'Quick setups and lighter builds'],
            ] as $profile): ?>
                <a class="polaris-profile-card" href="<?= e(url('find?stage=1')) ?>">
                    <strong><?= $this->e($profile[0]) ?></strong>
                    <span><?= $this->e($profile[1]) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-ink" aria-labelledby="how-polaris-works">
    <div class="container">
        <div class="section-heading">
            <span class="product-kicker">How Polaris works</span>
            <h2 id="how-polaris-works">Clear steps. Honest trade-offs.</h2>
        </div>
        <ol class="polaris-steps">
            <li><strong>Tell us how you travel.</strong> Lifestyle, tow vehicle and must-haves.</li>
            <li><strong>We assess genuine requirements.</strong> Hard constraints first, then preferences.</li>
            <li><strong>Compare clear, explained matches.</strong> Scores with reasons — not black-box rankings.</li>
        </ol>
    </div>
</section>

<section class="section product-section" aria-labelledby="tow-match-heading">
    <div class="container split-feature">
        <div>
            <span class="product-kicker dark">Tow Match</span>
            <h2 id="tow-match-heading">Compatibility is more than tow capacity.</h2>
            <p>Polaris uses TowSmart for vehicle data and calculation guidance. Headline ratings alone are never treated as a legal or safety guarantee.</p>
            <a class="btn btn-primary" href="<?= e(url('tow-match')) ?>">Open Tow Match</a>
        </div>
        <div class="polaris-callout">
            <p>Based on the figures and assumptions supplied, combinations are described as appearing within checked limits — confirm actual loaded weights before travel.</p>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="browse-models-heading">
    <div class="container">
        <div class="section-heading">
            <span class="product-kicker dark">Browse current models</span>
            <h2 id="browse-models-heading">Decision-useful model cards</h2>
            <p><?php if (!empty(array_filter($models ?? [], static fn ($m) => !empty($m['is_demo'])))): ?>Demonstration fixtures are labelled. <?php endif; ?>Pricing and specs show verification status where available.</p>
        </div>
        <?php if (empty($models)): ?>
            <p class="empty-state">No published models yet. Catalogue seed data appears after migration <code>087</code>.</p>
        <?php else: ?>
            <div class="polaris-model-grid">
                <?php foreach ($models as $model): ?>
                    <?php $this->include('polaris.partials.model-card', ['model' => $model]); ?>
                <?php endforeach; ?>
            </div>
            <p><a class="btn btn-ghost" href="<?= e(url('rvs')) ?>">Browse all new RVs</a></p>
        <?php endif; ?>
    </div>
</section>

<section class="section product-section" aria-labelledby="compare-heading">
    <div class="container split-feature">
        <div>
            <span class="product-kicker dark">Compare with confidence</span>
            <h2 id="compare-heading">Meaningful differences, not endless tables.</h2>
            <p>Compare up to four models with difference highlighting and missing-data clarity. Full personalised comparison ships in a later phase.</p>
            <a class="btn btn-secondary" href="<?= e(url('compare')) ?>">Open comparison</a>
        </div>
        <div class="polaris-compare-preview" aria-hidden="true">
            <div>Best payload</div>
            <div>Best off-grid</div>
            <div>Lightest tare</div>
        </div>
    </div>
</section>

<section class="section" aria-labelledby="trust-heading">
    <div class="container">
        <div class="section-heading">
            <span class="product-kicker dark">Trusted source transparency</span>
            <h2 id="trust-heading">Every important claim should be traceable.</h2>
        </div>
        <ul class="polaris-trust-list">
            <li>Manufacturer-supplied</li>
            <li>Public brochure</li>
            <li>Verified by manufacturer</li>
            <li>Community-confirmed</li>
            <li>Last reviewed date</li>
        </ul>
    </div>
</section>

<section class="section product-section" aria-labelledby="guides-heading">
    <div class="container">
        <div class="section-heading">
            <span class="product-kicker dark">Buying guides</span>
            <h2 id="guides-heading">Understand the numbers before you buy.</h2>
        </div>
        <div class="polaris-guide-grid">
            <?php foreach (['Payload', 'Caravan weights', 'Construction', 'Batteries and solar', 'Floorplans', 'Warranties', 'Towing compatibility'] as $i => $guide): ?>
                <?php
                $slugs = ['payload', 'caravan-weights', 'construction', 'batteries-and-solar', 'floorplans', 'warranties', 'towing-compatibility'];
                ?>
                <a href="<?= e(url('buying-guides/' . $slugs[$i])) ?>"><?= $this->e($guide) ?></a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section section-ink" aria-labelledby="mfr-heading">
    <div class="container split-feature">
        <div>
            <span class="product-kicker">Manufacturer participation</span>
            <h2 id="mfr-heading">Are you an Australian RV manufacturer?</h2>
            <p>Claim a prebuilt profile, review imported models, correct specifications and receive qualified buyer interest. Claim-first remains the rule — search existing profiles before creating duplicates.</p>
            <a class="btn btn-light" href="<?= e(url('portal/manufacturer')) ?>">Open manufacturer portal</a>
            <a class="btn btn-glass" href="<?= e(url('manufacturers')) ?>">View manufacturer profiles</a>
        </div>
    </div>
</section>

<section class="section product-cta" aria-labelledby="final-cta">
    <div class="container">
        <div>
            <span class="product-kicker dark">Ready when you are</span>
            <h2 id="final-cta">Start with how you travel.</h2>
            <p>Describe your trip in your own words, or answer a few guided questions.</p>
        </div>
        <a class="btn btn-primary btn-lg" href="<?= e(url('find')) ?>">Find my matches</a>
    </div>
</section>

<script>
(() => {
  const form = document.querySelector('[data-polaris-hero-form]');
  const field = document.getElementById('polaris-hero-prompt');
  if (!form || !field) return;
  const raw = field.getAttribute('data-polaris-examples') || '';
  const examples = raw.split('|').map(s => s.trim()).filter(Boolean);
  const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!reduce && examples.length > 1 && !field.value) {
    let i = 0;
    window.setInterval(() => {
      if (document.activeElement === field || field.value) return;
      i = (i + 1) % examples.length;
      field.placeholder = examples[i];
    }, 6000);
  }
  form.addEventListener('submit', () => {
    try { sessionStorage.setItem('polaris_hero_prompt', field.value); } catch (e) {}
  });
  try {
    const saved = sessionStorage.getItem('polaris_hero_prompt');
    if (saved && !field.value) field.value = saved;
  } catch (e) {}
})();
</script>

<?php $this->endSection(); ?>
