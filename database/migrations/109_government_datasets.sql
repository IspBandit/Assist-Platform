-- DATA-012: government dataset catalogue + traveller facility import queue.
-- Extends DATA-006 connector pattern. Review-first; never auto-publish facilities.
-- Does not overload caravan_parks (ADR 0016 / 0027).

CREATE TABLE IF NOT EXISTS government_datasets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_key VARCHAR(80) NOT NULL,
    publisher VARCHAR(190) NOT NULL,
    title VARCHAR(255) NOT NULL,
    coverage VARCHAR(120) NULL,
    record_types_json JSON NULL,
    licence VARCHAR(120) NULL,
    attribution VARCHAR(255) NULL,
    trust_policy ENUM('trusted_automatic','trusted_review','community_review','web_research_review','prohibited') NOT NULL DEFAULT 'trusted_review',
    fetch_method ENUM('ckan','arcgis','csv','geojson','url') NOT NULL,
    connector_key VARCHAR(80) NOT NULL,
    endpoint_url VARCHAR(1000) NULL,
    settings_json JSON NULL,
    default_facility_type VARCHAR(40) NOT NULL DEFAULT 'other_essential',
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_checked_at DATETIME NULL,
    last_imported_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_gov_dataset_key (dataset_key),
    KEY idx_gov_dataset_enabled (is_enabled, fetch_method)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS traveller_facility_import_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dataset_id INT UNSIGNED NULL,
    connector_key VARCHAR(80) NOT NULL,
    brand_id INT UNSIGNED NULL,
    status ENUM('queued','running','review','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
    scope_json JSON NULL,
    candidates_found INT UNSIGNED NOT NULL DEFAULT 0,
    candidates_new INT UNSIGNED NOT NULL DEFAULT 0,
    requested_by INT UNSIGNED NULL,
    error_message VARCHAR(1000) NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_tf_job_status (status, created_at),
    CONSTRAINT fk_tf_job_dataset FOREIGN KEY (dataset_id) REFERENCES government_datasets (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_job_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_job_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS traveller_facility_import_candidates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    dataset_id INT UNSIGNED NULL,
    brand_id INT UNSIGNED NULL,
    external_id VARCHAR(255) NOT NULL,
    facility_type VARCHAR(40) NOT NULL,
    name VARCHAR(190) NOT NULL,
    formatted_address VARCHAR(500) NULL,
    locality VARCHAR(120) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    source_url VARCHAR(1000) NULL,
    source_licence VARCHAR(120) NULL,
    source_attribution VARCHAR(255) NULL,
    raw_json JSON NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 60,
    review_status ENUM('pending','approved','rejected','ignored') NOT NULL DEFAULT 'pending',
    duplicate_facility_id BIGINT UNSIGNED NULL,
    facility_id BIGINT UNSIGNED NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    review_notes VARCHAR(500) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tf_cand_ext (job_id, external_id),
    KEY idx_tf_cand_queue (review_status, created_at),
    KEY idx_tf_cand_type (facility_type, review_status),
    CONSTRAINT fk_tf_cand_job FOREIGN KEY (job_id) REFERENCES traveller_facility_import_jobs (id) ON DELETE CASCADE,
    CONSTRAINT fk_tf_cand_dataset FOREIGN KEY (dataset_id) REFERENCES government_datasets (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_cand_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_cand_dup FOREIGN KEY (duplicate_facility_id) REFERENCES traveller_facilities (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_cand_facility FOREIGN KEY (facility_id) REFERENCES traveller_facilities (id) ON DELETE SET NULL,
    CONSTRAINT fk_tf_cand_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO data_source_connectors (connector_key, name, connector_class, status, daily_request_limit, daily_budget_aud, created_at, updated_at)
VALUES
('gov_ckan', 'Government CKAN open data', 'App\\Platform\\DataSources\\Connectors\\CkanDatasetConnector', 'configured', 100, 0.00, NOW(), NOW()),
('gov_arcgis', 'Government ArcGIS Feature Service', 'App\\Platform\\DataSources\\Connectors\\ArcGisFeatureConnector', 'configured', 100, 0.00, NOW(), NOW()),
('gov_csv', 'Government CSV dataset', 'App\\Platform\\DataSources\\Connectors\\CsvDatasetConnector', 'configured', 0, 0.00, NOW(), NOW()),
('gov_geojson', 'Government GeoJSON dataset', 'App\\Platform\\DataSources\\Connectors\\GeoJsonDatasetConnector', 'configured', 0, 0.00, NOW(), NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name), connector_class = VALUES(connector_class), updated_at = NOW();

INSERT INTO government_datasets
    (dataset_key, publisher, title, coverage, record_types_json, licence, attribution, trust_policy, fetch_method, connector_key, endpoint_url, settings_json, default_facility_type, is_enabled, created_at)
VALUES
(
    'demo_geojson_dump_points',
    'Assist Platform (fixture)',
    'Demo dump points GeoJSON (local fixture)',
    'AU demo',
    JSON_ARRAY('dump_point'),
    'internal-demo',
    'Demonstration fixture — not an official government dataset',
    'trusted_review',
    'geojson',
    'gov_geojson',
    NULL,
    JSON_OBJECT('default_facility_type', 'dump_point', 'name_field', 'name', 'type_field', 'facility_type', 'id_field', 'id'),
    'dump_point',
    0,
    NOW()
),
(
    'demo_csv_public_toilets',
    'Assist Platform (fixture)',
    'Demo public toilets CSV (local fixture)',
    'AU demo',
    JSON_ARRAY('public_toilet'),
    'internal-demo',
    'Demonstration fixture — not an official government dataset',
    'trusted_review',
    'csv',
    'gov_csv',
    NULL,
    JSON_OBJECT('default_facility_type', 'public_toilet', 'name_field', 'name', 'type_field', 'facility_type', 'id_field', 'id', 'lat_field', 'latitude', 'lng_field', 'longitude', 'address_field', 'address'),
    'public_toilet',
    0,
    NOW()
)
ON DUPLICATE KEY UPDATE title = VALUES(title), updated_at = NOW();
