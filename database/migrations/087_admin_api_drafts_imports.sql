-- CORE-011 Increment 7: Admin API draft and import staging tables.

CREATE TABLE api_drafts (
    id CHAR(36) NOT NULL,
    entity_type ENUM('provider', 'stay') NOT NULL,
    status ENUM('draft', 'pending_review', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'draft',
    payload_json JSON NOT NULL,
    source_system VARCHAR(64) NOT NULL DEFAULT 'ric',
    source_package_id VARCHAR(128) NULL,
    checksum CHAR(64) NULL,
    brand_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_client_id CHAR(36) NULL,
    reviewed_by INT UNSIGNED NULL,
    review_note TEXT NULL,
    live_entity_id INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    reviewed_at DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_api_drafts_brand_status (brand_id, status, created_at),
    KEY idx_api_drafts_entity (entity_type, live_entity_id),
    CONSTRAINT fk_api_drafts_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_drafts_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_api_drafts_client FOREIGN KEY (created_by_client_id) REFERENCES api_oauth_clients (id) ON DELETE SET NULL,
    CONSTRAINT fk_api_drafts_reviewer FOREIGN KEY (reviewed_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_import_jobs (
    id CHAR(36) NOT NULL,
    status ENUM('received', 'validated', 'staged', 'failed', 'cancelled') NOT NULL DEFAULT 'received',
    package_checksum CHAR(64) NOT NULL,
    item_count INT UNSIGNED NOT NULL DEFAULT 0,
    brand_id INT UNSIGNED NOT NULL,
    created_by_user_id INT UNSIGNED NULL,
    created_by_client_id CHAR(36) NULL,
    meta_json JSON NULL,
    error_json JSON NULL,
    idempotency_key VARCHAR(128) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_import_jobs_idempotency (brand_id, idempotency_key),
    KEY idx_api_import_jobs_brand_status (brand_id, status, created_at),
    CONSTRAINT fk_api_import_jobs_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_import_jobs_user FOREIGN KEY (created_by_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_api_import_jobs_client FOREIGN KEY (created_by_client_id) REFERENCES api_oauth_clients (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_import_job_items (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    job_id CHAR(36) NOT NULL,
    line_no INT UNSIGNED NOT NULL,
    entity_type ENUM('provider', 'stay') NOT NULL,
    status ENUM('pending', 'valid', 'invalid', 'staged', 'failed') NOT NULL DEFAULT 'pending',
    payload_json JSON NOT NULL,
    error_json JSON NULL,
    draft_id CHAR(36) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_import_job_items_line (job_id, line_no),
    KEY idx_api_import_job_items_draft (draft_id),
    CONSTRAINT fk_api_import_job_items_job FOREIGN KEY (job_id) REFERENCES api_import_jobs (id) ON DELETE CASCADE,
    CONSTRAINT fk_api_import_job_items_draft FOREIGN KEY (draft_id) REFERENCES api_drafts (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_idempotency_keys (
    id CHAR(36) NOT NULL,
    brand_id INT UNSIGNED NOT NULL,
    scope_key VARCHAR(64) NOT NULL,
    idempotency_key VARCHAR(128) NOT NULL,
    response_json JSON NOT NULL,
    created_at DATETIME NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_api_idempotency_keys (brand_id, scope_key, idempotency_key),
    CONSTRAINT fk_api_idempotency_keys_brand FOREIGN KEY (brand_id) REFERENCES brands (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
