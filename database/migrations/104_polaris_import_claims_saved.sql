-- Polaris Phase 6–9: import jobs, claims, saved items, analytics events foundation (POL-006…POL-009).

ALTER TABLE polaris_manufacturers
    ADD COLUMN claimed_by_user_id INT UNSIGNED NULL AFTER claim_status,
    ADD COLUMN claimed_at DATETIME NULL AFTER claimed_by_user_id,
    ADD KEY idx_polaris_mfr_claimed_by (claimed_by_user_id);

CREATE TABLE polaris_import_jobs (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    source_id               INT UNSIGNED NULL,
    manufacturer_id         INT UNSIGNED NULL,
    job_type                ENUM('csv','xlsx','json','manual','brochure','webpage') NOT NULL DEFAULT 'csv',
    status                  ENUM('queued','running','awaiting_review','published','rejected','failed') NOT NULL DEFAULT 'queued',
    progress_pct            TINYINT UNSIGNED NOT NULL DEFAULT 0,
    original_filename       VARCHAR(255) NULL,
    storage_path            VARCHAR(500) NULL,
    extractor_version       VARCHAR(40) NULL,
    prompt_version          VARCHAR(40) NULL,
    provider_key            VARCHAR(40) NULL,
    token_cost              INT UNSIGNED NULL,
    row_count               INT UNSIGNED NOT NULL DEFAULT 0,
    error_count             INT UNSIGNED NOT NULL DEFAULT 0,
    confidence_avg          DECIMAL(5,2) NULL,
    output_json             LONGTEXT NULL,
    validation_errors_json  LONGTEXT NULL,
    reviewer_user_id        INT UNSIGNED NULL,
    reviewed_at             DATETIME NULL,
    review_notes            TEXT NULL,
    started_at              DATETIME NULL,
    completed_at            DATETIME NULL,
    created_by_user_id      INT UNSIGNED NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_import_status (brand_id, status),
    CONSTRAINT fk_polaris_import_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT fk_polaris_import_source FOREIGN KEY (source_id) REFERENCES polaris_data_sources (id) ON DELETE SET NULL,
    CONSTRAINT fk_polaris_import_mfr FOREIGN KEY (manufacturer_id) REFERENCES polaris_manufacturers (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_import_drafts (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id                  INT UNSIGNED NOT NULL,
    draft_type              ENUM('manufacturer','model','variant','specification') NOT NULL,
    payload_json            LONGTEXT NOT NULL,
    confidence              TINYINT UNSIGNED NULL,
    review_status           ENUM('pending','approved','rejected','merged') NOT NULL DEFAULT 'pending',
    published_entity_type   VARCHAR(40) NULL,
    published_entity_id     INT UNSIGNED NULL,
    reviewer_user_id        INT UNSIGNED NULL,
    reviewed_at             DATETIME NULL,
    notes                   TEXT NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_draft_job (job_id, review_status),
    CONSTRAINT fk_polaris_draft_job FOREIGN KEY (job_id) REFERENCES polaris_import_jobs (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_manufacturer_claims (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    manufacturer_id         INT UNSIGNED NOT NULL,
    user_id                 INT UNSIGNED NOT NULL,
    status                  ENUM('pending','approved','rejected','withdrawn') NOT NULL DEFAULT 'pending',
    authority_evidence      TEXT NULL,
    contact_email           VARCHAR(190) NULL,
    reviewer_user_id        INT UNSIGNED NULL,
    reviewed_at             DATETIME NULL,
    review_notes            TEXT NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_claim_mfr (manufacturer_id, status),
    KEY idx_polaris_claim_user (user_id, status),
    CONSTRAINT fk_polaris_claim_brand FOREIGN KEY (brand_id) REFERENCES brands (id),
    CONSTRAINT fk_polaris_claim_mfr FOREIGN KEY (manufacturer_id) REFERENCES polaris_manufacturers (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_claim_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_saved_models (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                 INT UNSIGNED NOT NULL,
    model_id                INT UNSIGNED NOT NULL,
    notes                   VARCHAR(500) NULL,
    created_at              DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_polaris_saved_model (user_id, model_id),
    CONSTRAINT fk_polaris_saved_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_polaris_saved_model FOREIGN KEY (model_id) REFERENCES polaris_rv_models (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_saved_searches (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                 INT UNSIGNED NOT NULL,
    name                    VARCHAR(120) NOT NULL,
    query_json              TEXT NOT NULL,
    alert_enabled           TINYINT(1) NOT NULL DEFAULT 0,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_saved_search_user (user_id),
    CONSTRAINT fk_polaris_saved_search_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_preference_profiles (
    id                      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id                 INT UNSIGNED NULL,
    session_key             VARCHAR(64) NULL,
    profile_json            TEXT NOT NULL,
    last_score_version      VARCHAR(40) NULL,
    created_at              DATETIME NOT NULL,
    updated_at              DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_pref_user (user_id),
    KEY idx_polaris_pref_session (session_key),
    CONSTRAINT fk_polaris_pref_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE polaris_analytics_events (
    id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    brand_id                INT UNSIGNED NOT NULL,
    event_name              VARCHAR(80) NOT NULL,
    user_id                 INT UNSIGNED NULL,
    session_key             VARCHAR(64) NULL,
    entity_type             VARCHAR(40) NULL,
    entity_id               INT UNSIGNED NULL,
    properties_json         TEXT NULL,
    privacy_class           ENUM('anonymous','authenticated','sensitive') NOT NULL DEFAULT 'anonymous',
    created_at              DATETIME NOT NULL,
    PRIMARY KEY (id),
    KEY idx_polaris_evt_brand_name (brand_id, event_name, created_at),
    CONSTRAINT fk_polaris_evt_brand FOREIGN KEY (brand_id) REFERENCES brands (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissions (slug, name, perm_group, description, created_at) VALUES
('polaris.manufacturer', 'Manage claimed Polaris manufacturer profile', 'polaris', 'Manufacturer portal access for claimed profiles', NOW())
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT IGNORE INTO role_permissions (role_id, permission_id, created_at)
SELECT r.id, p.id, NOW() FROM roles r CROSS JOIN permissions p
WHERE r.slug IN ('super-administrator', 'administrator', 'platform-administrator')
  AND p.slug = 'polaris.manufacturer';

INSERT INTO feature_flags (flag_key, is_enabled, description, updated_at)
VALUES ('polaris_import_csv', 1, 'Allow CSV draft import for Polaris catalogue', NOW())
ON DUPLICATE KEY UPDATE description = VALUES(description);
