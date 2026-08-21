-- Generic, review-first external data ingestion for Assist Platform Enterprise.

CREATE TABLE data_source_connectors (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_key VARCHAR(80) NOT NULL,
    name VARCHAR(120) NOT NULL,
    connector_class VARCHAR(190) NOT NULL,
    status ENUM('disabled','configured','active','error') NOT NULL DEFAULT 'disabled',
    daily_request_limit INT UNSIGNED NOT NULL DEFAULT 100,
    daily_budget_aud DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estimated_request_cost_aud DECIMAL(10,4) NOT NULL DEFAULT 0.0000,
    settings_json JSON NULL,
    last_error VARCHAR(500) NULL,
    last_used_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_source_connector_key (connector_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE provider_discovery_evidence
    ADD COLUMN connector_key VARCHAR(80) NULL AFTER source_type,
    ADD KEY idx_provider_discovery_connector (connector_key, source_reference);

CREATE TABLE data_source_credentials (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_id INT UNSIGNED NOT NULL,
    credential_key VARCHAR(80) NOT NULL,
    encrypted_value MEDIUMTEXT NOT NULL,
    value_hint VARCHAR(20) NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_source_credential (connector_id, credential_key),
    CONSTRAINT fk_data_source_credential_connector FOREIGN KEY (connector_id) REFERENCES data_source_connectors (id) ON DELETE CASCADE,
    CONSTRAINT fk_data_source_credential_user FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_source_category_mappings (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    external_query VARCHAR(190) NOT NULL,
    external_types_json JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_source_category_mapping (connector_id, brand_id, category_id),
    CONSTRAINT fk_data_source_mapping_connector FOREIGN KEY (connector_id) REFERENCES data_source_connectors (id) ON DELETE CASCADE,
    CONSTRAINT fk_data_source_mapping_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_data_source_mapping_category FOREIGN KEY (category_id) REFERENCES brand_provider_categories (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_source_import_jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    mapping_id INT UNSIGNED NULL,
    status ENUM('queued','running','review','completed','failed','cancelled') NOT NULL DEFAULT 'queued',
    scope_json JSON NOT NULL,
    requests_used INT UNSIGNED NOT NULL DEFAULT 0,
    candidates_found INT UNSIGNED NOT NULL DEFAULT 0,
    candidates_new INT UNSIGNED NOT NULL DEFAULT 0,
    requested_by INT UNSIGNED NULL,
    started_at DATETIME NULL,
    completed_at DATETIME NULL,
    error_message VARCHAR(1000) NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_data_source_jobs_review (brand_id, status, created_at),
    CONSTRAINT fk_data_source_job_connector FOREIGN KEY (connector_id) REFERENCES data_source_connectors (id) ON DELETE RESTRICT,
    CONSTRAINT fk_data_source_job_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_data_source_job_mapping FOREIGN KEY (mapping_id) REFERENCES data_source_category_mappings (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_source_job_user FOREIGN KEY (requested_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_source_import_candidates (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id BIGINT UNSIGNED NOT NULL,
    connector_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NULL,
    external_id VARCHAR(255) NOT NULL,
    business_name VARCHAR(190) NOT NULL,
    formatted_address VARCHAR(500) NULL,
    phone VARCHAR(40) NULL,
    website VARCHAR(500) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    raw_json JSON NOT NULL,
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 0,
    review_status ENUM('pending','approved','merged','rejected','ignored') NOT NULL DEFAULT 'pending',
    duplicate_provider_id INT UNSIGNED NULL,
    duplicate_score TINYINT UNSIGNED NULL,
    duplicate_reasons_json JSON NULL,
    provider_id INT UNSIGNED NULL,
    reviewed_by INT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    expires_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_data_source_candidate (connector_id, brand_id, external_id),
    KEY idx_data_source_candidate_queue (brand_id, review_status, confidence, created_at),
    KEY idx_data_source_candidate_expiry (expires_at, review_status),
    CONSTRAINT fk_data_source_candidate_job FOREIGN KEY (job_id) REFERENCES data_source_import_jobs (id) ON DELETE CASCADE,
    CONSTRAINT fk_data_source_candidate_connector FOREIGN KEY (connector_id) REFERENCES data_source_connectors (id) ON DELETE RESTRICT,
    CONSTRAINT fk_data_source_candidate_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_data_source_candidate_category FOREIGN KEY (category_id) REFERENCES brand_provider_categories (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_source_candidate_duplicate FOREIGN KEY (duplicate_provider_id) REFERENCES providers (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_source_candidate_provider FOREIGN KEY (provider_id) REFERENCES providers (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_source_candidate_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_source_usage_daily (
    connector_id INT UNSIGNED NOT NULL,
    usage_date DATE NOT NULL,
    requests_used INT UNSIGNED NOT NULL DEFAULT 0,
    estimated_cost_aud DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    updated_at DATETIME NOT NULL,
    PRIMARY KEY (connector_id, usage_date),
    CONSTRAINT fk_data_source_usage_connector FOREIGN KEY (connector_id) REFERENCES data_source_connectors (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE data_source_schedules (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    connector_id INT UNSIGNED NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    mapping_id INT UNSIGNED NULL,
    name VARCHAR(150) NOT NULL,
    frequency ENUM('daily','weekly','monthly') NOT NULL DEFAULT 'weekly',
    scope_json JSON NOT NULL,
    is_enabled TINYINT(1) NOT NULL DEFAULT 0,
    last_run_at DATETIME NULL,
    next_run_at DATETIME NULL,
    created_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_data_source_schedule_due (is_enabled, next_run_at),
    CONSTRAINT fk_data_source_schedule_connector FOREIGN KEY (connector_id) REFERENCES data_source_connectors (id) ON DELETE CASCADE,
    CONSTRAINT fk_data_source_schedule_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE RESTRICT,
    CONSTRAINT fk_data_source_schedule_mapping FOREIGN KEY (mapping_id) REFERENCES data_source_category_mappings (id) ON DELETE SET NULL,
    CONSTRAINT fk_data_source_schedule_user FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO data_source_connectors (connector_key, name, connector_class, status, daily_request_limit, daily_budget_aud, estimated_request_cost_aud, settings_json, created_at)
VALUES ('google_places', 'Google Places', 'App\\Platform\\DataSources\\Connectors\\GooglePlacesConnector', 'disabled', 100, 10.00, 0.05, JSON_OBJECT('region_code','AU','language_code','en'), NOW());

INSERT IGNORE INTO permissions (slug, name, perm_group, created_at) VALUES
('data_sources.view', 'View external data sources', 'platform', NOW()),
('data_sources.manage', 'Manage external data source credentials and configuration', 'platform', NOW()),
('data_sources.run', 'Run external data source imports', 'platform', NOW()),
('data_sources.review', 'Review and merge external data candidates', 'platform', NOW());

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('platform-administrator', 'administrator') AND p.slug LIKE 'data_sources.%';
