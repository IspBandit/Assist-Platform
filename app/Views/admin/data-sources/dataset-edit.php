<?php
/** @var \App\Core\View $this */
/** @var array<string,mixed>|null $dataset */
/** @var array<string,mixed> $settings */
$this->extend('layouts.admin');
$isEdit = is_array($dataset);
?>
<?php $this->section('content'); ?>
<div class="page-header">
    <div>
        <p class="eyebrow">Data Sources</p>
        <h1><?= $isEdit ? 'Edit government dataset' : 'Add government dataset' ?></h1>
        <p class="muted">Catalogue metadata only. Fetch and approve remain separate review-first steps.</p>
    </div>
    <div class="btn-row">
        <a class="btn btn-ghost" href="<?= e(url('admin/data-sources/datasets')) ?>">Back to catalogue</a>
    </div>
</div>

<section class="card">
    <form method="post" action="<?= e(url('admin/data-sources/datasets/upsert')) ?>" class="form-stack">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $isEdit ? (int) $dataset['id'] : 0 ?>">

        <div class="form-grid">
            <label>Dataset key
                <input name="dataset_key" required value="<?= e_attr((string) ($dataset['dataset_key'] ?? '')) ?>" placeholder="au_example_toilets" <?= $isEdit ? 'readonly' : '' ?>>
            </label>
            <label>Publisher
                <input name="publisher" required value="<?= e_attr((string) ($dataset['publisher'] ?? '')) ?>">
            </label>
        </div>
        <label>Title / dataset name
            <input name="title" required value="<?= e_attr((string) ($dataset['title'] ?? '')) ?>">
        </label>
        <div class="form-grid">
            <label>Coverage
                <input name="coverage" value="<?= e_attr((string) ($dataset['coverage'] ?? '')) ?>" placeholder="AU national / QLD / …">
            </label>
            <label>Jurisdiction
                <input name="jurisdiction" value="<?= e_attr((string) ($dataset['jurisdiction'] ?? '')) ?>" placeholder="AU / QLD / NSW / …">
            </label>
            <label>Record / entity types (comma-separated)
                <input name="record_types" value="<?= e_attr(implode(', ', (array) (json_decode((string) ($dataset['record_types_json'] ?? '[]'), true) ?: [($dataset['default_facility_type'] ?? 'public_toilet')]))) ?>">
            </label>
        </div>
        <div class="form-grid">
            <label>Licence
                <input name="licence" value="<?= e_attr((string) ($dataset['licence'] ?? '')) ?>">
            </label>
            <label>Attribution
                <input name="attribution" value="<?= e_attr((string) ($dataset['attribution'] ?? '')) ?>">
            </label>
        </div>
        <div class="form-grid">
            <label>Trust policy / level
                <select name="trust_policy">
                    <?php foreach (['trusted_review', 'community_review', 'web_research_review', 'prohibited'] as $policy): ?>
                        <option value="<?= e($policy) ?>" <?= (($dataset['trust_policy'] ?? 'trusted_review') === $policy) ? 'selected' : '' ?>><?= e($policy) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Catalogue status
                <select name="catalogue_status">
                    <?php foreach (['planned', 'indexed', 'active', 'paused', 'retired'] as $status): ?>
                        <option value="<?= e($status) ?>" <?= (($dataset['catalogue_status'] ?? 'planned') === $status) ? 'selected' : '' ?>><?= e($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Default facility type
                <input name="default_facility_type" value="<?= e_attr((string) ($dataset['default_facility_type'] ?? 'public_toilet')) ?>">
            </label>
        </div>
        <div class="form-grid">
            <label>Fetch method
                <select name="fetch_method" required>
                    <?php foreach (['ckan', 'arcgis', 'csv', 'geojson', 'url'] as $method): ?>
                        <option value="<?= e($method) ?>" <?= (($dataset['fetch_method'] ?? 'ckan') === $method) ? 'selected' : '' ?>><?= e($method) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Source format
                <input name="source_format" value="<?= e_attr((string) ($dataset['source_format'] ?? ($settings['format'] ?? ''))) ?>" placeholder="CSV / GeoJSON / portal / osm">
            </label>
            <label>Update frequency
                <input name="update_frequency" value="<?= e_attr((string) ($dataset['update_frequency'] ?? '')) ?>" placeholder="daily / weekly / continuous / irregular">
            </label>
            <label>Connector
                <select name="connector_key" required>
                    <?php foreach (['gov_ckan', 'gov_arcgis', 'gov_csv', 'gov_geojson', 'osm_offline_seed'] as $ck): ?>
                        <option value="<?= e($ck) ?>" <?= (($dataset['connector_key'] ?? 'gov_ckan') === $ck) ? 'selected' : '' ?>><?= e($ck) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>
        <label>Source URL (landing / publisher page)
            <input name="source_url" value="<?= e_attr((string) ($dataset['source_url'] ?? '')) ?>" placeholder="https://data.gov.au/data/dataset/…">
        </label>
        <label>API URL / endpoint
            <input name="endpoint_url" value="<?= e_attr((string) ($dataset['endpoint_url'] ?? '')) ?>" placeholder="https://data.gov.au/data/api">
        </label>
        <label>Duplicate rules JSON
            <textarea name="duplicate_rules_json" rows="3" placeholder='{"match_on":["source_record_id","geo_proximity"],"geo_metres":25}'><?= e((string) ($dataset['duplicate_rules_json'] ?? '')) ?></textarea>
        </label>
        <label>Notes
            <textarea name="notes" rows="3"><?= e((string) ($dataset['notes'] ?? '')) ?></textarea>
        </label>

        <h2 class="h3">Connector settings</h2>
        <p class="muted">For CKAN, prefer stable <code>package_api_url</code> + <code>resource_id</code> (download filenames rotate).</p>
        <div class="form-grid">
            <label>package_api_url
                <input name="package_api_url" value="<?= e_attr((string) ($settings['package_api_url'] ?? '')) ?>" placeholder="https://data.gov.au/data/api">
            </label>
            <label>resource_id
                <input name="resource_id" value="<?= e_attr((string) ($settings['resource_id'] ?? '')) ?>">
            </label>
            <label>resource_url (optional direct)
                <input name="resource_url" value="<?= e_attr((string) ($settings['resource_url'] ?? '')) ?>">
            </label>
            <label>feature_url (ArcGIS)
                <input name="feature_url" value="<?= e_attr((string) ($settings['feature_url'] ?? '')) ?>">
            </label>
            <label>name_field
                <input name="name_field" value="<?= e_attr((string) ($settings['name_field'] ?? 'name')) ?>">
            </label>
            <label>id_field
                <input name="id_field" value="<?= e_attr((string) ($settings['id_field'] ?? 'facilityid')) ?>">
            </label>
            <label>lat_field
                <input name="lat_field" value="<?= e_attr((string) ($settings['lat_field'] ?? 'latitude')) ?>">
            </label>
            <label>lng_field
                <input name="lng_field" value="<?= e_attr((string) ($settings['lng_field'] ?? 'longitude')) ?>">
            </label>
            <label>address_field
                <input name="address_field" value="<?= e_attr((string) ($settings['address_field'] ?? 'address1')) ?>">
            </label>
            <label>type_field
                <input name="type_field" value="<?= e_attr((string) ($settings['type_field'] ?? '')) ?>">
            </label>
            <label>filter_field
                <input name="filter_field" value="<?= e_attr((string) ($settings['filter_field'] ?? '')) ?>" placeholder="dumppoint">
            </label>
            <label>filter_value
                <input name="filter_value" value="<?= e_attr((string) ($settings['filter_value'] ?? '')) ?>" placeholder="true">
            </label>
            <label>format
                <input name="format" value="<?= e_attr((string) ($settings['format'] ?? 'csv')) ?>">
            </label>
            <label>Row limit
                <input type="number" min="1" max="500" name="limit" value="<?= e_attr((string) ($settings['limit'] ?? 100)) ?>">
            </label>
        </div>
        <label>Advanced settings JSON (optional merge override)
            <textarea name="settings_json" rows="4" placeholder='{"extra":"optional"}'></textarea>
        </label>
        <label>
            <input type="checkbox" name="is_enabled" value="1" <?= !empty($dataset['is_enabled']) ? 'checked' : '' ?>>
            Enable for Fetch (still review-first; never auto-publish)
        </label>
        <label>
            <input type="checkbox" name="auto_update_enabled" value="1" <?= !empty($dataset['auto_update_enabled']) ? 'checked' : '' ?>>
            Auto update enabled for RIC (Platform still never auto-publishes)
        </label>
        <button class="btn btn-primary" type="submit">Save catalogue row</button>
    </form>
</section>
<?php $this->endSection(); ?>
