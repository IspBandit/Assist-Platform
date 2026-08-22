<?php
/**
 * @var array<string,array{name:string,description:string,disciplines:array<string,string>}> $families
 * @var array<string,string> $jurisdictions
 * @var array<string,string> $ruleTypes
 * @var array{jurisdiction:string,discipline:string,family:string,rule_type:string,q:string} $filters
 * @var array<string,int> $coverage
 * @var array<int,array<string,mixed>> $documents
 * @var array<int,array<string,mixed>> $venues
 */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>

<section class="motorsport-hero">
    <picture class="journey-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" type="image/avif" srcset="<?= e(asset('img/motorsport-hero-mobile.avif')) ?>">
        <source media="(max-width: 719px)" type="image/webp" srcset="<?= e(asset('img/motorsport-hero-mobile.webp')) ?>">
        <source type="image/avif" srcset="<?= e(asset('img/motorsport-hero-desktop.avif')) ?>">
        <img src="<?= e(asset('img/motorsport-hero-desktop.webp')) ?>" width="1824" height="864" alt="" fetchpriority="high">
    </picture>
    <div class="journey-hero-shade" aria-hidden="true"></div>
    <div class="container motorsport-hero-inner">
        <span class="product-kicker">LocalTorque motorsport</span>
        <h1>Know the rulebook before you build, enter or race.</h1>
        <p>Official Australian competition, technical, safety and licensing sources across more than 50 car, kart and motorcycle disciplines.</p>
        <div class="rules-hero-actions">
            <a class="btn btn-light btn-lg" href="#motorsport-search">Find my rules</a>
            <a class="btn btn-glass btn-lg" href="#discipline-map">Browse every category</a>
            <a class="btn btn-glass btn-lg" href="#rule-layers">How rule layers work</a>
        </div>
        <ul class="motorsport-hero-proof" aria-label="Library coverage">
            <li><strong><?= count($families) ?></strong><span>discipline families</span></li>
            <li><strong><?= count(\App\Services\MotorsportCatalogue::disciplines()) ?>+</strong><span>named disciplines</span></li>
            <li><strong>9</strong><span>jurisdictions</span></li>
        </ul>
    </div>
</section>

<section class="motorsport-layers" id="rule-layers">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">The complete rule path</span>
            <h2>One race can be governed by several documents.</h2>
            <p>LocalTorque keeps these layers separate so a national manual is never presented as the only rule that matters.</p>
        </div>
        <ol class="motorsport-layer-grid">
            <li><span>01</span><strong>Sanctioning body</strong><p>National competition and judicial rules.</p></li>
            <li><span>02</span><strong>Discipline & class</strong><p>Vehicle eligibility, technical and safety specifications.</p></li>
            <li><span>03</span><strong>State or series</strong><p>Regional championship and series requirements.</p></li>
            <li><span>04</span><strong>Event & venue</strong><p>Supplementary regulations, bulletins and venue instructions.</p></li>
        </ol>
    </div>
</section>

<section class="section motorsport-disciplines" id="discipline-map">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">Every category is explicit</span>
            <h2>Choose the discipline you actually compete in.</h2>
            <p>Similar-looking sports can use different licences, log books, vehicle classes and safety standards.</p>
        </div>
        <div class="motorsport-family-grid">
            <?php foreach ($families as $familyKey => $family): ?>
                <details class="motorsport-family-card"<?= $filters['family'] === $familyKey ? ' open' : '' ?>>
                    <summary class="motorsport-family-heading">
                        <span><?= str_pad((string) (array_search($familyKey, array_keys($families), true) + 1), 2, '0', STR_PAD_LEFT) ?></span>
                        <div><h3><?= $this->e($family['name']) ?></h3><p><?= $this->e($family['description']) ?></p><small><?= count($family['disciplines']) ?> disciplines</small></div>
                        <b aria-hidden="true">+</b>
                    </summary>
                    <ul>
                        <?php foreach ($family['disciplines'] as $key => $name): ?>
                            <li><a href="<?= e(url('motorsport?discipline=' . rawurlencode($key) . '#motorsport-results')) ?>"><?= $this->e($name) ?><span aria-hidden="true">→</span></a></li>
                        <?php endforeach; ?>
                    </ul>
                </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="section motorsport-library" id="motorsport-search">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">Official rule finder</span>
            <h2>Build the rule set for your event.</h2>
            <p>Select the discipline and location. Always read the organiser’s current supplementary regulations and bulletins before entering.</p>
        </div>
        <form class="motorsport-filter" method="get" action="<?= e(url('motorsport')) ?>" role="search">
            <label><span>Discipline</span><select name="discipline"><option value="">All disciplines</option>
                <?php foreach ($families as $family): ?><optgroup label="<?= $this->e($family['name']) ?>"><?php foreach ($family['disciplines'] as $key => $name): ?><option value="<?= $this->e($key) ?>"<?= $filters['discipline'] === $key ? ' selected' : '' ?>><?= $this->e($name) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?>
            </select></label>
            <label><span>Jurisdiction</span><select name="jurisdiction"><option value="">All Australia</option><?php foreach ($jurisdictions as $key => $name): ?><option value="<?= $this->e($key) ?>"<?= $filters['jurisdiction'] === $key ? ' selected' : '' ?>><?= $this->e($name) ?></option><?php endforeach; ?></select></label>
            <label><span>Rule layer</span><select name="rule_type"><option value="">All rule layers</option><?php foreach ($ruleTypes as $key => $name): ?><option value="<?= $this->e($key) ?>"<?= $filters['rule_type'] === $key ? ' selected' : '' ?>><?= $this->e($name) ?></option><?php endforeach; ?></select></label>
            <label><span>Keywords</span><input type="search" name="q" value="<?= $this->e($filters['q']) ?>" maxlength="100" placeholder="e.g. roll cage, licence, junior"></label>
            <button class="btn btn-primary" type="submit">Find official rules</button>
            <?php if ($filters['discipline'] !== '' || $filters['jurisdiction'] !== '' || $filters['rule_type'] !== '' || $filters['q'] !== ''): ?><a class="rules-clear" href="<?= e(url('motorsport')) ?>">Clear filters</a><?php endif; ?>
        </form>

        <div class="motorsport-coverage" aria-label="Jurisdiction source coverage">
            <?php foreach ($jurisdictions as $code => $name): ?><a href="<?= e(url('motorsport?jurisdiction=' . rawurlencode($code) . '#motorsport-results')) ?>"<?= $filters['jurisdiction'] === $code ? ' aria-current="page"' : '' ?>><strong><?= $this->e($code) ?></strong><span><?= (int) ($coverage[$code] ?? 0) ?> sources</span></a><?php endforeach; ?>
        </div>

        <div class="rules-result-heading" id="motorsport-results">
            <div><strong><?= count($documents) ?></strong><span>official source<?= count($documents) === 1 ? '' : 's' ?></span></div>
            <p>Source links open on the governing body’s website.</p>
        </div>
        <?php if ($documents === []): ?>
            <div class="empty-state rules-empty"><h2>No official source matches every filter.</h2><p>Broaden the rule layer or jurisdiction. LocalTorque shows a gap instead of inventing coverage.</p><a class="btn btn-primary" href="<?= e(url('motorsport')) ?>">View all sources</a></div>
        <?php else: ?>
            <div class="motorsport-document-grid">
                <?php foreach ($documents as $document):
                    $jurisdictionCodes = json_decode((string) $document['jurisdictions_json'], true);
                    $ruleKeys = json_decode((string) $document['rule_types_json'], true);
                    $familyKeys = array_filter(explode(',', (string) ($document['family_keys'] ?? '')));
                    $checked = !empty($document['last_checked_at']) ? date('j M Y', strtotime((string) $document['last_checked_at'])) : 'Check queued';
                    $primary = !empty($document['download_url']) ? (string) $document['download_url'] : (string) $document['source_url'];
                ?>
                    <article class="motorsport-document-card">
                        <div class="rule-card-topline"><span class="rule-jurisdiction"><?= $this->e(strtoupper((string) $document['document_level'])) ?></span><span class="rule-status rule-status--current">Current</span></div>
                        <p class="rule-kind"><?= $this->e((string) $document['authority_name']) ?></p>
                        <h3><?= $this->e((string) $document['title']) ?></h3>
                        <p><?= $this->e((string) $document['summary']) ?></p>
                        <div class="rule-vehicles"><?php foreach ($familyKeys as $key): ?><span><?= $this->e($families[$key]['name'] ?? $key) ?></span><?php endforeach; ?></div>
                        <dl class="rule-meta">
                            <div><dt>Jurisdictions</dt><dd><?= $this->e(implode(', ', is_array($jurisdictionCodes) ? $jurisdictionCodes : [])) ?></dd></div>
                            <div><dt>Rule layers</dt><dd><?= $this->e(implode(', ', array_map(static fn (string $key): string => $ruleTypes[$key] ?? $key, is_array($ruleKeys) ? $ruleKeys : []))) ?></dd></div>
                            <?php if (!empty($document['version_label'])): ?><div><dt>Version</dt><dd><?= $this->e((string) $document['version_label']) ?></dd></div><?php endif; ?>
                            <div><dt>Source checked</dt><dd><?= $this->e($checked) ?></dd></div>
                        </dl>
                        <div class="rule-actions"><a class="btn btn-primary" href="<?= $this->e($primary) ?>" target="_blank" rel="noopener noreferrer"><?= !empty($document['download_url']) ? 'Download official rulebook' : 'Open official rules' ?></a><?php if (!empty($document['download_url'])): ?><a href="<?= $this->e((string) $document['source_url']) ?>" target="_blank" rel="noopener noreferrer">Authority page</a><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>

<section class="section motorsport-venues" id="motorsport-venues">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">Where to compete</span>
            <h2>Venues and official event calendars.</h2>
            <p><?= $filters['discipline'] !== '' ? 'Showing venues relevant to ' . $this->e(\App\Services\MotorsportCatalogue::disciplines()[$filters['discipline']] ?? $filters['discipline']) . '.' : 'Browse verified permanent venues plus official location and calendar sources for route-based and temporary events.' ?> Calendar dates remain on the venue, club or governing body website so late changes stay with the source.</p>
        </div>
        <div class="motorsport-venue-summary"><strong><?= count($venues) ?></strong><span>relevant venue<?= count($venues) === 1 ? '' : 's' ?> or location sources</span><a href="#motorsport-search">Change discipline or state</a></div>
        <?php if ($venues === []): ?>
            <div class="empty-state rules-empty"><h2>No verified venue source matches those filters yet.</h2><p>The gap remains visible until an official venue, club or governing-body source is verified.</p></div>
        <?php else: ?>
            <div class="motorsport-venue-grid">
                <?php foreach ($venues as $venue):
                    $venueFamilies = array_filter(explode(',', (string) ($venue['family_keys'] ?? '')));
                    $typeLabel = match ((string) $venue['venue_type']) {
                        'event_route' => 'Event-specific route',
                        'club_network' => 'Club or temporary site',
                        'temporary' => 'Temporary venue',
                        default => 'Permanent venue',
                    };
                ?>
                    <article class="motorsport-venue-card">
                        <div class="motorsport-venue-top"><span><?= $this->e((string) $venue['jurisdiction_code']) ?></span><small><?= $this->e($typeLabel) ?></small></div>
                        <h3><?= $this->e((string) $venue['name']) ?></h3>
                        <p class="motorsport-venue-place"><?= $this->e((string) $venue['locality']) ?></p>
                        <p><?= $this->e((string) $venue['description']) ?></p>
                        <div class="rule-vehicles"><?php foreach ($venueFamilies as $key): ?><span><?= $this->e($families[$key]['name'] ?? $key) ?></span><?php endforeach; ?></div>
                        <div class="motorsport-venue-actions">
                            <?php if (!empty($venue['website_url'])): ?><a href="<?= $this->e((string) $venue['website_url']) ?>" target="_blank" rel="noopener noreferrer">Venue website</a><?php endif; ?>
                            <?php if (!empty($venue['calendar_url'])): ?><a class="btn btn-primary" href="<?= $this->e((string) $venue['calendar_url']) ?>" target="_blank" rel="noopener noreferrer">View official calendar</a><?php else: ?><span>Calendar not published</span><?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p class="motorsport-calendar-note"><strong>Before travelling:</strong> confirm the event date, venue access, sanctioning body, supplementary regulations and entry status with the organiser. A calendar listing is not confirmation that entries remain open.</p>
    </div>
</section>

<section class="motorsport-next">
    <div class="container"><div><span class="product-kicker">Build with the right specialist</span><h2>From rulebook to race-ready.</h2><p>Find fabricators, dyno tuners, race preparation workshops, roll-cage specialists, motorcycle technicians and transport providers near you.</p></div><a class="btn btn-light btn-lg" href="<?= e(url('providers')) ?>">Find motorsport services</a></div>
</section>

<section class="rules-legal"><div class="container"><strong>Important:</strong><p>Motorsport rules are not road-registration approval. Requirements vary by sanctioning body, discipline, class, series, venue and event. The current rulebook, technical bulletins, event supplementary regulations and officials’ directions always govern. LocalTorque links to official sources and does not issue competition eligibility, licences, log books or scrutineering approval.</p></div></section>

<?php $this->endSection(); ?>
