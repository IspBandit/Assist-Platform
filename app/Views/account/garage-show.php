<?php
/** @var array<string,mixed> $asset */
/** @var array<int,array<string,mixed>> $documents */
/** @var array<string,string> $types */
/** @var array<string,string> $jurisdictions */
/** @var array<string,string> $documentTypes */
/** @var string $rulesVehicle */
$this->extend('layouts.public');
$rulesQuery = array_filter([
    'jurisdiction' => (string) ($asset['registration_jurisdiction'] ?? ''),
    'vehicle' => $rulesVehicle,
]);
$rulesUrl = url('rules') . ($rulesQuery === [] ? '' : '?' . http_build_query($rulesQuery));
$makeModel = trim((string) ($asset['make'] ?? '') . ' ' . (string) ($asset['model'] ?? ''));
?>
<?php $this->section('content'); ?>
<section class="garage-detail-hero">
    <div class="container">
        <a class="back-link" href="<?= e(url('account/garage')) ?>">← My Garage</a>
        <div class="garage-detail-heading">
            <div>
                <span class="product-kicker"><?= $this->e($types[(string) $asset['asset_type']] ?? 'Vehicle or towable') ?></span>
                <h1><?= $this->e((string) $asset['nickname']) ?></h1>
                <p><?= $this->e($makeModel !== '' ? trim((string) ($asset['model_year'] ?? '') . ' ' . $makeModel) : 'Your shared vehicle profile') ?></p>
            </div>
            <div class="garage-origin"><span>Added through</span><strong><?= $this->e((string) $asset['created_in_brand_name']) ?></strong><small>Available across all Assist brands</small></div>
        </div>
    </div>
</section>

<section class="section garage-section">
    <div class="container garage-detail-grid">
        <main>
            <section class="garage-action-panel" aria-labelledby="garage-next-actions">
                <div><span class="eyebrow">Relevant next actions</span><h2 id="garage-next-actions">Use this profile now</h2></div>
                <div class="garage-action-grid">
                    <a href="<?= $this->e($rulesUrl) ?>"><strong>Open official rules</strong><span>Filtered for <?= $this->e((string) ($asset['registration_jurisdiction'] ?: 'Australia')) ?> and this asset type</span><b aria-hidden="true">→</b></a>
                    <a href="<?= e(url('providers')) ?>"><strong>Find relevant providers</strong><span>Continue into <?= $this->e(current_brand()->name()) ?> provider search</span><b aria-hidden="true">→</b></a>
                    <?php if (current_brand()->id() === 'towsmart'): ?><a href="<?= e(url('calculator')) ?>"><strong>Start a weight check</strong><span>Use verified plate ratings when building the combination</span><b aria-hidden="true">→</b></a><?php endif; ?>
                </div>
            </section>

            <section class="card garage-wallet" aria-labelledby="wallet-title">
                <div class="garage-section-heading"><div><span class="eyebrow">Private compliance wallet</span><h2 id="wallet-title">Documents</h2></div><span class="badge badge-neutral"><?= count($documents) ?> saved</span></div>
                <?php if ($documents === []): ?>
                    <div class="garage-wallet-empty"><p>Keep registration, insurance, roadworthy, engineering and modification documents together for this asset.</p></div>
                <?php else: ?>
                    <div class="garage-document-list">
                        <?php foreach ($documents as $document): ?>
                            <?php $expired = $document['expires_at'] !== null && (string) $document['expires_at'] < date('Y-m-d'); ?>
                            <article class="garage-document<?= $expired ? ' is-expired' : '' ?>">
                                <div><span class="garage-document-type"><?= $this->e($documentTypes[(string) $document['document_type']] ?? 'Document') ?></span><h3><?= $this->e((string) $document['label']) ?></h3><p><?= $document['expires_at'] ? ($expired ? 'Expired ' : 'Expires ') . $this->e(date('j M Y', strtotime((string) $document['expires_at']))) : 'No expiry recorded' ?></p></div>
                                <div class="garage-document-actions"><a class="btn btn-secondary" href="<?= e(url('account/garage/document?id=' . (int) $document['id'])) ?>">Download</a><form method="post" action="<?= e(url('account/garage/document/remove')) ?>"><?= csrf_field() ?><input type="hidden" name="document_id" value="<?= (int) $document['id'] ?>"><button class="text-button" type="submit">Remove</button></form></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <details class="garage-upload">
                    <summary>Add a private document</summary>
                    <form method="post" enctype="multipart/form-data" action="<?= e(url('account/garage/' . (int) $asset['id'] . '/documents')) ?>">
                        <?= csrf_field() ?>
                        <div class="form-grid form-grid-2">
                            <div class="form-group"><label for="document_type">Document type</label><select id="document_type" name="document_type"><?php foreach ($documentTypes as $value => $label): ?><option value="<?= $this->e($value) ?>"><?= $this->e($label) ?></option><?php endforeach; ?></select></div>
                            <div class="form-group"><label for="document_label">Label</label><input id="document_label" name="label" maxlength="150" placeholder="For example: 2026 registration"></div>
                            <div class="form-group"><label for="issuing_authority">Issuer <span>optional</span></label><input id="issuing_authority" name="issuing_authority" maxlength="150"></div>
                            <div class="form-group"><label for="issue_date">Issue date <span>optional</span></label><input id="issue_date" name="issue_date" type="date"></div>
                            <div class="form-group"><label for="expires_at">Expiry date <span>optional</span></label><input id="expires_at" name="expires_at" type="date"></div>
                            <div class="form-group"><label for="document">PDF or image</label><input id="document" name="document" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" required></div>
                        </div>
                        <p class="form-help">Files are stored privately and are only served after an account ownership check. Maximum <?= (int) config('uploads.max_document_mb', 10) ?> MB.</p>
                        <button class="btn btn-primary" type="submit">Add to wallet</button>
                    </form>
                </details>
            </section>
        </main>

        <aside class="garage-detail-aside">
            <details class="card garage-edit" open>
                <summary>Edit profile</summary>
                <form method="post" action="<?= e(url('account/garage/' . (int) $asset['id'])) ?>">
                    <?= csrf_field() ?>
                    <div class="form-group"><label for="asset_type">Type</label><select id="asset_type" name="asset_type" required><?php foreach ($types as $value => $label): ?><option value="<?= $this->e($value) ?>"<?= (string) $asset['asset_type'] === $value ? ' selected' : '' ?>><?= $this->e($label) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label for="nickname">Garage name</label><input id="nickname" name="nickname" maxlength="100" required value="<?= e_attr((string) $asset['nickname']) ?>"></div>
                    <div class="form-grid form-grid-2">
                        <div class="form-group"><label for="make">Make</label><input id="make" name="make" maxlength="100" value="<?= e_attr((string) ($asset['make'] ?? '')) ?>"></div>
                        <div class="form-group"><label for="model">Model</label><input id="model" name="model" maxlength="100" value="<?= e_attr((string) ($asset['model'] ?? '')) ?>"></div>
                        <div class="form-group"><label for="model_year">Year</label><input id="model_year" name="model_year" type="number" min="1900" max="<?= (int) date('Y') + 2 ?>" value="<?= e_attr((string) ($asset['model_year'] ?? '')) ?>"></div>
                        <div class="form-group"><label for="registration_jurisdiction">State</label><select id="registration_jurisdiction" name="registration_jurisdiction"><option value="">Not set</option><?php foreach ($jurisdictions as $value => $label): ?><option value="<?= $this->e($value) ?>"<?= (string) ($asset['registration_jurisdiction'] ?? '') === $value ? ' selected' : '' ?>><?= $this->e($value) ?></option><?php endforeach; ?></select></div>
                    </div>
                    <details class="garage-technical-fields"><summary>Plate ratings</summary><div class="form-grid form-grid-2"><?php foreach (['tare_kg' => 'Tare', 'gvm_kg' => 'GVM', 'gcm_kg' => 'GCM', 'atm_kg' => 'ATM', 'max_braked_towing_kg' => 'Max braked towing', 'max_towball_kg' => 'Max towball'] as $field => $label): ?><div class="form-group"><label for="<?= $field ?>"><?= $label ?> (kg)</label><input id="<?= $field ?>" name="<?= $field ?>" type="number" min="0" max="9999999" step="0.1" value="<?= e_attr((string) ($asset[$field] ?? '')) ?>"></div><?php endforeach; ?></div></details>
                    <div class="form-group"><label for="notes">Private notes</label><textarea id="notes" name="notes" maxlength="2000" rows="4"><?= $this->e((string) ($asset['notes'] ?? '')) ?></textarea></div>
                    <button class="btn btn-primary" type="submit">Save details</button>
                </form>
            </details>
            <details class="garage-danger"><summary>Remove from Garage</summary><p>This hides the profile. Private files are retained until account-data deletion.</p><form method="post" action="<?= e(url('account/garage/' . (int) $asset['id'] . '/remove')) ?>"><?= csrf_field() ?><button class="btn btn-danger" type="submit">Remove asset</button></form></details>
        </aside>
    </div>
</section>
<?php $this->endSection(); ?>
