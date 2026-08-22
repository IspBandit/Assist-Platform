<?php
/**
 * @var array<int,array<string,mixed>> $documents
 * @var array<string,int> $coverage
 * @var array<string,string> $jurisdictions
 * @var array<string,string> $vehicles
 * @var array<string,string> $kinds
 * @var array{jurisdiction:string,vehicle:string,kind:string,q:string,town:int} $filters
 * @var array<string,mixed>|null $selectedTown
 * @var array<int,array<string,mixed>> $sponsoredCampaigns
 * @var array{title:string,metaDescription:string,kicker:string,heading:string,intro:string,vehicleSummary:string} $page
 * @var string $heroAsset
 */
$this->extend('layouts.public');
?>
<?php $this->section('content'); ?>

<section class="rules-hero">
    <picture class="journey-hero-media" aria-hidden="true">
        <source media="(max-width: 719px)" type="image/avif" srcset="<?= e(asset('img/' . $heroAsset . '-mobile.avif')) ?>">
        <source media="(max-width: 719px)" type="image/webp" srcset="<?= e(asset('img/' . $heroAsset . '-mobile.webp')) ?>">
        <source type="image/avif" srcset="<?= e(asset('img/' . $heroAsset . '-desktop.avif')) ?>">
        <img src="<?= e(asset('img/' . $heroAsset . '-desktop.webp')) ?>" width="1824" height="864" alt="" fetchpriority="high">
    </picture>
    <div class="journey-hero-shade" aria-hidden="true"></div>
    <div class="container rules-hero-grid">
        <div>
            <span class="product-kicker"><?= $this->e($page['kicker']) ?></span>
            <h1><?= $this->e($page['heading']) ?></h1>
            <p><?= $this->e($page['intro']) ?></p>
            <div class="rules-hero-actions">
                <a class="btn btn-light btn-lg" href="<?= e(url('rules/guided')) ?>">Build a guided check</a>
                <a class="btn btn-glass btn-lg" href="#rules-results">Search all rules</a>
                <a class="btn btn-glass btn-lg" href="#how-current">How updates work</a>
            </div>
        </div>
        <aside class="rules-trust-panel" aria-label="Library trust commitments">
            <span class="rules-trust-eyebrow">Authority first</span>
            <strong>Official sources. Genuine downloads.</strong>
            <ul>
                <li>Federal plus every state and territory</li>
                <li><?= $this->e($page['vehicleSummary']) ?></li>
                <li>Version and effective-date tracking</li>
                <li>Changed sources removed for human review</li>
            </ul>
        </aside>
    </div>
</section>

<section class="rules-coverage" aria-label="Jurisdiction coverage">
    <div class="container rules-coverage-row">
        <?php foreach ($jurisdictions as $code => $name): ?>
            <a href="<?= e(url('rules?jurisdiction=' . rawurlencode($code))) ?>"<?= $filters['jurisdiction'] === $code ? ' aria-current="page"' : '' ?>>
                <strong><?= $this->e($code) ?></strong>
                <span><?= (int) ($coverage[$code] ?? 0) ?> sources</span>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="section rules-library" id="rules-results">
    <div class="container">
        <div class="section-heading compact">
            <span class="product-kicker dark">Official regulatory sources</span>
            <h2>Find the official rule that applies.</h2>
            <p>Choose a jurisdiction first. National technical codes do not replace state and territory approval, inspection or registration requirements.</p>
        </div>

        <form class="rules-filter" method="get" action="<?= e(url('rules')) ?>" role="search">
            <label>
                <span>Jurisdiction</span>
                <select name="jurisdiction">
                    <option value="">All jurisdictions</option>
                    <?php foreach ($jurisdictions as $value => $label): ?>
                        <option value="<?= $this->e($value) ?>"<?= $filters['jurisdiction'] === $value ? ' selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Vehicle</span>
                <select name="vehicle">
                    <option value="">All vehicle types</option>
                    <?php foreach ($vehicles as $value => $label): ?>
                        <option value="<?= $this->e($value) ?>"<?= $filters['vehicle'] === $value ? ' selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>Rule type</span>
                <select name="kind">
                    <option value="">All rule types</option>
                    <?php foreach ($kinds as $value => $label): ?>
                        <option value="<?= $this->e($value) ?>"<?= $filters['kind'] === $value ? ' selected' : '' ?>><?= $this->e($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="rules-filter-search">
                <span>Keywords</span>
                <input type="search" name="q" value="<?= $this->e($filters['q']) ?>" maxlength="100" placeholder="e.g. suspension, brakes, street rod">
            </label>
            <label class="rules-filter-location">
                <span>Your area <small>for local sponsors</small></span>
                <span class="location-field">
                    <input id="rules-town-search" name="location" type="search" value="<?= $this->e($selectedTown !== null ? trim((string) $selectedTown['name'] . ' / ' . (string) $selectedTown['state_abbr']) : '') ?>" placeholder="Town, suburb or postcode" autocomplete="off" data-town-search="<?= e_attr(url('locations/towns')) ?>" aria-autocomplete="list" aria-controls="rules-town-suggest">
                    <input type="hidden" id="town_id" name="town" value="<?= $selectedTown !== null ? (int) $selectedTown['id'] : '' ?>">
                    <span id="rules-town-suggest" class="town-suggest" role="listbox" hidden></span>
                </span>
            </label>
            <button class="btn btn-primary" type="submit">Find rules</button>
            <?php if (array_filter($filters, static fn ($value): bool => $value !== '' && $value !== 0) !== []): ?><a class="rules-clear" href="<?= e(url('rules')) ?>">Clear filters</a><?php endif; ?>
        </form>

        <div class="rules-result-heading">
            <div>
                <strong><?= count($documents) ?></strong>
                <span>official <?= count($documents) === 1 ? 'resource' : 'resources' ?></span>
            </div>
            <p>Downloads open on the issuing authority’s website.</p>
        </div>

        <?php if ($documents === []): ?>
            <div class="empty-state rules-empty">
                <h2>No official sources match those filters.</h2>
                <p>Try a broader vehicle type or view all jurisdictions. This library never invents a rule to fill a gap.</p>
                <a class="btn btn-primary" href="<?= e(url('rules')) ?>">View all rules</a>
            </div>
        <?php else: ?>
            <div class="rules-grid">
                <?php foreach ($documents as $document): ?>
                    <?php
                    $classes = json_decode((string) $document['vehicle_classes_json'], true);
                    $classes = is_array($classes) ? $classes : [];
                    $isPdf = !empty($document['download_url']);
                    $primaryUrl = $isPdf ? (string) $document['download_url'] : (string) $document['source_url'];
                    $checked = !empty($document['last_checked_at'])
                        ? date('j M Y', strtotime((string) $document['last_checked_at']))
                        : 'First check queued';
                    ?>
                    <article class="rule-card">
                        <div class="rule-card-topline">
                            <span class="rule-jurisdiction"><?= $this->e((string) $document['jurisdiction_code']) ?></span>
                            <span class="rule-status rule-status--<?= $this->e((string) $document['publication_status']) ?>">
                                <?= $document['publication_status'] === 'upcoming' ? 'Upcoming' : 'Current' ?>
                            </span>
                        </div>
                        <p class="rule-kind"><?= $this->e($kinds[(string) $document['document_kind']] ?? (string) $document['document_kind']) ?></p>
                        <h3><?= $this->e((string) $document['title']) ?></h3>
                        <p class="rule-summary"><?= $this->e((string) $document['summary']) ?></p>
                        <div class="rule-vehicles" aria-label="Applicable vehicle types">
                            <?php foreach ($classes as $vehicle): ?>
                                <span><?= $this->e($vehicles[(string) $vehicle] ?? ucwords(str_replace('-', ' ', (string) $vehicle))) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <dl class="rule-meta">
                            <div><dt>Issued by</dt><dd><?= $this->e((string) $document['authority_name']) ?></dd></div>
                            <?php if (!empty($document['version_label'])): ?><div><dt>Version</dt><dd><?= $this->e((string) $document['version_label']) ?></dd></div><?php endif; ?>
                            <?php if (!empty($document['effective_from'])): ?><div><dt>Effective</dt><dd><?= $this->e(date('j M Y', strtotime((string) $document['effective_from']))) ?></dd></div><?php endif; ?>
                            <div><dt>Source check</dt><dd><?= $this->e($checked) ?></dd></div>
                        </dl>
                        <div class="rule-actions">
                            <a class="btn btn-primary" href="<?= $this->e($primaryUrl) ?>" target="_blank" rel="noopener noreferrer">
                                <?= $isPdf ? 'Download official PDF' : 'View official rules' ?>
                            </a>
                            <?php if ($isPdf): ?>
                                <a href="<?= $this->e((string) $document['source_url']) ?>" target="_blank" rel="noopener noreferrer">Authority page</a>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($sponsoredCampaigns !== []): ?>
            <aside class="rules-sponsors" aria-label="Sponsored local specialists">
                <div class="rules-sponsors-heading">
                    <div><span>Sponsored</span><h2>Relevant specialists<?= $selectedTown !== null ? ' near ' . $this->e((string) $selectedTown['name']) : '' ?></h2></div>
                    <p>Paid placements from providers. Sponsorship does not influence the official rules above or organic directory rankings.</p>
                </div>
                <div class="rules-sponsor-grid">
                    <?php foreach ($sponsoredCampaigns as $campaign): ?>
                            <a href="<?= e(url('sponsor/' . (int) $campaign['id'] . '/click')) ?>" target="_blank" rel="sponsored noopener noreferrer">
                            <small>Sponsored provider</small>
                            <strong><?= $this->e((string) $campaign['headline']) ?></strong>
                            <?php if (!empty($campaign['body'])): ?><span><?= $this->e((string) $campaign['body']) ?></span><?php endif; ?>
                            <?php if (!empty($campaign['provider_name'])): ?><em><?= $this->e((string) $campaign['provider_name']) ?> →</em><?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </aside>
        <?php endif; ?>
    </div>
</section>

<section class="section rules-current" id="how-current">
    <div class="container rules-current-grid">
        <div>
            <span class="product-kicker dark">Current means checked</span>
            <h2>A living source register, not a forgotten links page.</h2>
            <p><?= $this->e(current_brand()->name()) ?> fingerprints each official page or document on a schedule. When its bytes change, the affected record leaves the public library until the new version, effective date and plain-language description have been reviewed.</p>
        </div>
        <ol>
            <li><span>01</span><div><strong>Monitor</strong><p>Check official authority pages and downloads daily.</p></div></li>
            <li><span>02</span><div><strong>Detect</strong><p>Compare source fingerprints, versions and effective dates.</p></div></li>
            <li><span>03</span><div><strong>Review</strong><p>Hold changed material back until a person verifies it.</p></div></li>
            <li><span>04</span><div><strong>Publish</strong><p>Restore the verified official link with a clear current status.</p></div></li>
        </ol>
    </div>
</section>

<section class="rules-legal">
    <div class="container">
        <strong>Important:</strong>
        <p><?= $this->e(current_brand()->name()) ?> provides access to official sources and general navigation help. It does not provide legal advice, engineering certification, a roadworthy certificate or approval to modify a vehicle. Requirements depend on the vehicle, manufacture date, intended modification and jurisdiction. The issuing authority’s current material always governs.</p>
    </div>
</section>

<?php $this->endSection(); ?>
